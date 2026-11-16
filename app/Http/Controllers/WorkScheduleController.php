<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WorkScheduleController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manageContracts', $employee);

        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'alternating' => ['nullable', 'boolean'],
            'days' => ['required', 'array'],
            'days.*.*.start' => ['nullable', 'date_format:H:i'],
            'days.*.*.end' => ['nullable', 'date_format:H:i'],
            'days.*.*.break' => ['nullable', 'integer', 'between:0,480'],
        ], [], ['effective_from' => 'ingangsdatum']);

        $alternating = $request->boolean('alternating');
        $pattern = ['odd' => $this->cleanWeek($data['days']['odd'] ?? [])];

        if ($alternating) {
            $pattern['even'] = $this->cleanWeek($data['days']['even'] ?? []);
        }

        if (WorkSchedule::dayHours(null) === 0.0 && array_sum(array_map(fn ($week) => count($week), $pattern)) === 0) {
            throw ValidationException::withMessages(['days' => 'Vul minimaal één werkdag in.']);
        }

        $employee->schedules()->updateOrCreate(
            ['effective_from' => $data['effective_from']],
            ['alternating' => $alternating, 'pattern' => $pattern],
        );

        return redirect()->route('employees.show', [$employee, 'tab' => 'schedule'])->with('status', 'Rooster opgeslagen.');
    }

    public function destroy(Employee $employee, WorkSchedule $schedule): RedirectResponse
    {
        Gate::authorize('manageContracts', $employee);

        $schedule->delete();

        return redirect()->route('employees.show', [$employee, 'tab' => 'schedule'])->with('status', 'Rooster verwijderd.');
    }

    /**
     * Keep only days that have both a start and end time.
     *
     * @param  array<int|string, array<string, mixed>>  $week
     * @return array<int, array{start: string, end: string, break: int}>
     */
    private function cleanWeek(array $week): array
    {
        $clean = [];

        foreach ($week as $weekday => $day) {
            if (empty($day['start']) || empty($day['end'])) {
                continue;
            }

            if ($day['end'] <= $day['start']) {
                throw ValidationException::withMessages(['days' => 'De eindtijd moet na de starttijd liggen.']);
            }

            $clean[(int) $weekday] = ['start' => $day['start'], 'end' => $day['end'], 'break' => (int) ($day['break'] ?? 0)];
        }

        return $clean;
    }
}
