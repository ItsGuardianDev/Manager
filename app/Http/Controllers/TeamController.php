<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        Gate::authorize('create', Employee::class);

        return view('teams.index', [
            'teams' => Team::with('manager')->withCount('employees')->orderBy('name')->get(),
            'managers' => Employee::employed()->orderBy('last_name')->get()->pluck('full_name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Employee::class);

        Team::create($this->validated($request));

        return back()->with('status', 'Team aangemaakt.');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('create', Employee::class);

        $team->update($this->validated($request, $team));

        return back()->with('status', 'Team opgeslagen.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        Gate::authorize('create', Employee::class);

        $team->delete();

        return back()->with('status', 'Team verwijderd.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Team $team = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('teams', 'name')->ignore($team)],
            'manager_id' => ['nullable', 'exists:employees,id'],
        ], [], ['name' => 'naam', 'manager_id' => 'teammanager']);
    }
}
