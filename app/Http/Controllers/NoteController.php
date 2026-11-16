<?php

namespace App\Http\Controllers;

use App\Enums\NoteType;
use App\Models\Employee;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    public function storeForOrganization(Request $request, Organization $organization): RedirectResponse
    {
        return $this->store($request, $organization);
    }

    public function storeForPerson(Request $request, Person $person): RedirectResponse
    {
        return $this->store($request, $person);
    }

    public function storeForEmployee(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('addNote', $employee);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'type' => ['required', Rule::enum(NoteType::class)],
        ]);

        $employee->notes()->create([...$data, 'user_id' => $request->user()->id]);

        return redirect()->route('employees.show', [$employee, 'tab' => 'timeline'])->with('status', 'Notitie toegevoegd.');
    }

    public function destroy(Note $note): RedirectResponse
    {
        if ($note->noteable instanceof Employee) {
            Gate::authorize('addNote', $note->noteable);
        }

        $note->delete();

        return back()->with('status', 'Notitie verwijderd.');
    }

    private function store(Request $request, Organization|Person $noteable): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $noteable->notes()->create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('status', 'Notitie toegevoegd.');
    }
}
