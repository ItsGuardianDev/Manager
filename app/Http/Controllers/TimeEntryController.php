<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Services\TimeRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Registers hours straight from a project. The rules live in TimeRegistrar, shared with the hours module.
 */
class TimeEntryController extends Controller
{
    public function store(Request $request, Project $project, TimeRegistrar $registrar): RedirectResponse
    {
        Gate::authorize('projects.view');

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'project_service_id' => ['required', 'exists:project_services,id'],
            'hour_type_id' => ['nullable', 'exists:hour_types,id'],
            'date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'gt:0', 'max:24'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['employee_id' => 'medewerker', 'project_service_id' => 'dienst', 'hour_type_id' => 'urensoort', 'date' => 'datum', 'hours' => 'uren']);

        $registrar->create($request->user(), [...$data, 'project_id' => $project->id]);

        return redirect()->route('projects.show', [$project, 'tab' => 'hours'])->with('status', 'Uren geregistreerd.');
    }

    public function destroy(Request $request, Project $project, TimeEntry $timeEntry, TimeRegistrar $registrar): RedirectResponse
    {
        $registrar->delete($request->user(), $timeEntry, $request->input('correction_reason'));

        return redirect()->route('projects.show', [$project, 'tab' => 'hours'])->with('status', 'Registratie verwijderd.');
    }
}
