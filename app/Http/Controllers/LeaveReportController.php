<?php

namespace App\Http\Controllers;

use App\Enums\BalanceKind;
use App\Enums\LedgerReason;
use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\Employee;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveReportController extends Controller
{
    use HandlesCsv;

    private const HEADERS = [
        'Personeelsnummer', 'Medewerker', 'Team', 'Vakantie toegekend', 'Vakantie opgenomen', 'Saldo vakantie',
        'Saldo tijd-voor-tijd', 'Verzuimmeldingen', 'Verzuimuren', 'Verzuimpercentage',
    ];

    public function index(Request $request): View
    {
        Gate::authorize('reports.view');

        [$year, $rows] = $this->build($request);

        return view('reports.leave', [
            'year' => $year,
            'rows' => $rows,
            'teams' => Team::orderBy('name')->pluck('name', 'id'),
            'totals' => [
                'taken' => $rows->sum('taken'),
                'absence_hours' => $rows->sum('absence_hours'),
                'scheduled' => $rows->sum('scheduled'),
            ],
            'headers' => self::HEADERS,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('reports.view');

        [$year, $rows] = $this->build($request);

        return $this->streamCsv("verlof-verzuim-{$year}.csv", self::HEADERS, $rows->map(fn (array $row) => [
            $row['employee']->employee_number, $row['employee']->full_name, $row['employee']->team?->name,
            $row['allocated'], $row['taken'], $row['vacation_balance'], $row['tvt_balance'],
            $row['absence_count'], $row['absence_hours'], $row['absence_percentage'].'%',
        ]));
    }

    /**
     * @return array{0: int, 1: Collection<int, array<string, mixed>>}
     */
    private function build(Request $request): array
    {
        $year = (int) $request->query('year', now()->year);
        $from = Carbon::create($year, 1, 1);
        $to = Carbon::create($year, 12, 31);
        $until = $to->gt(today()) ? today() : $to;

        $employees = Employee::query()
            ->with(['team', 'schedules', 'absences' => fn ($query) => $query
                ->whereDate('start_date', '<=', $to)
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $from))])
            ->with(['ledgerEntries'])
            ->when($request->query('team_id'), fn ($query, $team) => $query->where('team_id', $team))
            ->where(fn ($query) => $query->whereNull('hired_at')->orWhereDate('hired_at', '<=', $to))
            ->where(fn ($query) => $query->whereNull('left_at')->orWhereDate('left_at', '>=', $from))
            ->orderBy('last_name')
            ->get();

        $rows = $employees->map(function (Employee $employee) use ($from, $until, $year): array {
            $vacation = $employee->ledgerEntries->where('kind', BalanceKind::Vacation);
            $inYear = $vacation->filter(fn ($entry) => $entry->entry_date->year === $year);

            $absenceHours = 0.0;
            $absenceCount = 0;
            foreach ($employee->absences as $absence) {
                $start = $absence->start_date->max($from);
                $end = ($absence->end_date ?? today())->min($until);

                if ($start->gt($end)) {
                    continue;
                }

                $absenceCount += $absence->start_date->year === $year ? 1 : 0;
                $absenceHours += $employee->scheduledHours($start, $end) * $absence->percentage / 100;
            }

            $scheduled = $employee->scheduledHours($from, $until);

            return [
                'employee' => $employee,
                'allocated' => round((float) $inYear->whereIn('reason', [LedgerReason::Allocation, LedgerReason::Carryover])->sum('hours'), 2),
                'taken' => round(-1 * (float) $inYear->where('reason', LedgerReason::Taken)->sum('hours'), 2),
                'vacation_balance' => round((float) $vacation->sum('hours'), 2),
                'tvt_balance' => round((float) $employee->ledgerEntries->where('kind', BalanceKind::TimeForTime)->sum('hours'), 2),
                'absence_count' => $absenceCount,
                'absence_hours' => round($absenceHours, 2),
                'scheduled' => $scheduled,
                'absence_percentage' => $scheduled > 0 ? round($absenceHours / $scheduled * 100, 1) : 0.0,
            ];
        });

        return [$year, $rows];
    }
}
