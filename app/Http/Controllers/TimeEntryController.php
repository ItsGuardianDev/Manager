<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\HourType;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\TimeEntry;
use App\Services\BudgetMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Registers hours on a project service. The dedicated hours module builds on the same entries.
 */
class TimeEntryController extends Controller
{
    public function store(Request $request, Project $project, BudgetMonitor $monitor): RedirectResponse
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

        $employee = Employee::findOrFail($data['employee_id']);
        $service = ProjectService::where('project_id', $project->id)->findOrFail($data['project_service_id']);

        abort_unless($this->mayRegisterFor($request, $employee), 403);

        if ($project->status->value !== 'active') {
            throw ValidationException::withMessages(['hours' => 'Er kunnen geen uren meer worden geregistreerd op een afgerond of gesloten project.']);
        }

        if (! $project->allowsEmployee($employee)) {
            throw ValidationException::withMessages(['employee_id' => 'Deze medewerker is niet toegewezen aan dit project.']);
        }

        $rate = $service->hourLines()->where('hour_type_id', $data['hour_type_id'] ?? 0)->value('rate')
            ?? (isset($data['hour_type_id']) ? HourType::find($data['hour_type_id'])->default_rate : 0);

        $project->timeEntries()->create([...$data, 'rate' => $rate]);
        $monitor->check($project);

        return redirect()->route('projects.show', [$project, 'tab' => 'hours'])->with('status', 'Uren geregistreerd.');
    }

    public function destroy(Request $request, Project $project, TimeEntry $timeEntry, BudgetMonitor $monitor): RedirectResponse
    {
        abort_unless($this->mayRegisterFor($request, $timeEntry->employee), 403);

        $timeEntry->delete();
        $monitor->check($project);

        return redirect()->route('projects.show', [$project, 'tab' => 'hours'])->with('status', 'Registratie verwijderd.');
    }

    private function mayRegisterFor(Request $request, Employee $employee): bool
    {
        $user = $request->user();

        return $user->can('projects.edit') || ($user->employee?->is($employee) && $user->can('projects.view'));
    }
}
