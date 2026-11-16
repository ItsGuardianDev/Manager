<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WorkScheduleController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manageContracts', $employee);

        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'cost_rate' => ['required', 'numeric', 'min:0', 'max:9999'],
            'sales_rate' => ['required', 'numeric', 'min:0', 'max:9999'],
            'alternating' => ['nullable', 'boolean'],
            'days' => ['required', 'array'],
            'days.*.*.start' => ['nullable', 'date_format:H:i'],
            'days.*.*.end' => ['nullable', 'date_format:H:i'],
            'days.*.*.break' => ['nullable', 'integer', 'between:0,480'],
        ], [], ['effective_from' => 'begindatum', 'cost_rate' => 'kostprijs', 'sales_rate' => 'verkooptarief']);

        $alternating = $request->boolean('alternating');
        $pattern = ['odd' => $this->cleanWeek($data['days']['odd'] ?? [])];

        if ($alternating) {
            $pattern['even'] = $this->cleanWeek($data['days']['even'] ?? []);
        }

        if (array_sum(array_map(fn ($week) => count($week), $pattern)) === 0) {
            throw ValidationException::withMessages(['days' => 'Vul minimaal één werkdag in.']);
        }

        $employee->schedules()->updateOrCreate(
            ['effective_from' => $data['effective_from']],
            ['alternating' => $alternating, 'pattern' => $pattern, 'cost_rate' => $data['cost_rate'], 'sales_rate' => $data['sales_rate']],
        );

        $employee->logEvent('Rooster per '.Carbon::parse($data['effective_from'])->format('d-m-Y').' opgeslagen met een verkooptarief van € '.number_format((float) $data['sales_rate'], 2, ',', '.').' en een kostprijs van € '.number_format((float) $data['cost_rate'], 2, ',', '.').'.');

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
