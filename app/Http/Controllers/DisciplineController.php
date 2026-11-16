<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DisciplineController extends Controller
{
    public function index(): View
    {
        Gate::authorize('create', Employee::class);

        return view('disciplines.index', ['disciplines' => Discipline::withCount('employees')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Employee::class);

        Discipline::create($this->validated($request));

        return back()->with('status', 'Discipline aangemaakt.');
    }

    public function update(Request $request, Discipline $discipline): RedirectResponse
    {
        Gate::authorize('create', Employee::class);

        $discipline->update($this->validated($request, $discipline));

        return back()->with('status', 'Discipline opgeslagen.');
    }

    public function destroy(Discipline $discipline): RedirectResponse
    {
        Gate::authorize('create', Employee::class);

        $discipline->delete();

        return back()->with('status', 'Discipline verwijderd.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Discipline $discipline = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('disciplines', 'name')->ignore($discipline)],
        ], [], ['name' => 'naam']);
    }
}
