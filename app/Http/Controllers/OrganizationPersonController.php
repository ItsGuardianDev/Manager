<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manages the company ↔ person link and the job function stored on it.
 */
class OrganizationPersonController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'person_id' => ['required', 'exists:people,id'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
        ]);

        $person = Person::findOrFail($data['person_id']);
        $organization = Organization::findOrFail($data['organization_id']);

        if ($person->organizations()->whereKey($organization->id)->exists()) {
            return back()->withErrors(['organization_id' => 'Deze persoon is al aan dat bedrijf gekoppeld.']);
        }

        $person->organizations()->attach($organization->id, [
            'job_title' => $data['job_title'] ?? null,
            'department' => $data['department'] ?? null,
            'is_primary' => $person->organizations()->doesntExist(),
        ]);

        $organization->logEvent("Contactpersoon {$person->full_name} gekoppeld".(($data['job_title'] ?? null) ? " als {$data['job_title']}" : '').'.');
        $person->logEvent("Gekoppeld als contactpersoon bij {$organization->name}.");

        return back()->with('status', "{$person->full_name} is gekoppeld aan {$organization->name}.");
    }

    public function update(Request $request, Organization $organization, Person $person): RedirectResponse
    {
        $data = $request->validate([
            'job_title' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_primary')) {
            $person->organizations()->newPivotStatement()
                ->where('person_id', $person->id)->update(['is_primary' => false]);
        }

        $person->organizations()->updateExistingPivot($organization->id, [
            'job_title' => $data['job_title'] ?? null,
            'department' => $data['department'] ?? null,
            'is_primary' => $request->boolean('is_primary'),
        ]);

        return back()->with('status', 'Koppeling bijgewerkt.');
    }

    public function destroy(Organization $organization, Person $person): RedirectResponse
    {
        $person->organizations()->detach($organization->id);
        $organization->logEvent("Contactpersoon {$person->full_name} ontkoppeld.");
        $person->logEvent("Ontkoppeld van {$organization->name}.");

        return back()->with('status', 'Koppeling verwijderd.');
    }
}
