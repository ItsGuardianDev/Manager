<?php

namespace App\Http\Controllers;

use App\Http\Requests\PersonRequest;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonController extends Controller
{
    public function index(Request $request): View
    {
        $sort = in_array($request->query('sort'), ['email', 'created_at'], true) ? $request->query('sort') : 'last_name';
        $direction = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $people = Person::query()
            ->with(['employee', 'organizations' => fn ($query) => $query->orderByDesc('organization_person.is_primary')->orderBy('name')])
            ->filter($request->query())
            ->orderBy($sort, $direction)
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('people.index', [
            'people' => $people,
            'sort' => $sort,
            'direction' => $direction,
            'organizations' => Organization::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('people.form', [
            'person' => new Person(['is_active' => true]),
            'organizations' => Organization::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'preselectedOrganizationId' => $request->query('organization_id'),
            'duplicate' => null,
            ...$this->lookups(),
        ]);
    }

    public function store(PersonRequest $request): RedirectResponse
    {
        $person = Person::create($request->safe()->except(['organization_id', 'job_title', 'confirm_duplicate', 'note', 'use_salutation']));
        $person->logEvent('Persoon aangemaakt door '.$request->user()->name.'.');

        if ($request->filled('note')) {
            $person->notes()->create(['body' => $request->input('note'), 'user_id' => $request->user()->id]);
        }

        if ($request->filled('organization_id')) {
            $person->organizations()->attach($request->integer('organization_id'), [
                'job_title' => $request->input('job_title'),
                'is_primary' => true,
            ]);
        }

        return redirect()->route('people.show', $person)->with('status', 'Persoon aangemaakt.');
    }

    public function show(Person $person, Request $request): View
    {
        $person->load(['organizations' => fn ($query) => $query->with('industry'), 'notes.author', 'documents.uploader', 'team', 'relationManager', 'employee']);

        return view('people.show', [
            'person' => $person,
            'tab' => $request->query('tab'),
            'availableOrganizations' => Organization::query()
                ->where('is_active', true)
                ->whereNotIn('id', $person->organizations->modelKeys())
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Person $person): View
    {
        return view('people.form', [
            'person' => $person,
            'organizations' => collect(),
            'preselectedOrganizationId' => null,
            'duplicate' => null,
            ...$this->lookups(),
        ]);
    }

    public function update(PersonRequest $request, Person $person): RedirectResponse
    {
        $person->update($request->safe()->except(['organization_id', 'job_title', 'confirm_duplicate', 'note', 'use_salutation']));
        $person->logEvent('Persoonsgegevens gewijzigd door '.$request->user()->name.'.');

        return redirect()->route('people.show', $person)->with('status', 'Persoon bijgewerkt.');
    }

    /**
     * People are never hard-deleted; this toggles the active flag.
     */
    public function toggleActive(Person $person): RedirectResponse
    {
        $person->update(['is_active' => ! $person->is_active]);
        $person->logEvent($person->is_active ? 'Persoon geactiveerd.' : 'Persoon gedeactiveerd.');

        return back()->with('status', $person->is_active ? 'Persoon geactiveerd.' : 'Persoon gedeactiveerd.');
    }

    /**
     * @return array<string, mixed>
     */
    private function lookups(): array
    {
        return [
            'teams' => Team::orderBy('name')->pluck('name', 'id'),
            'managers' => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
