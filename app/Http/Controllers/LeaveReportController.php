<?php

namespace App\Http\Controllers;

use App\Enums\BalanceKind;
use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\Employee;
use App\Models\Team;
use App\Services\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveReportController extends Controller
{
    use HandlesCsv;

    public function index(Request $request): View
    {
        Gate::authorize('reports.view');

        [$year, $unit, $rows] = $this->build($request);

        return view('reports.leave', [
            'year' => $year,
            'unit' => $unit,
            'rows' => $rows,
            'teams' => Team::orderBy('name')->pluck('name', 'id'),
            'totals' => [
                'taken' => $rows->sum('taken'),
                'absence_hours' => $rows->sum('absence_hours'),
                'scheduled' => $rows->sum('scheduled'),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|BinaryFileResponse
    {
        Gate::authorize('reports.view');

        [$year, $unit, $rows] = $this->build($request);
        $label = $unit === 'days' ? 'dagen' : 'uren';
        $headers = ['Personeelsnummer', 'Medewerker', 'Teams', "Saldo begin jaar ({$label})", "Toegekend ({$label})", "Opgenomen ({$label})", "Ingepland ({$label})",
            "Saldo einde jaar ({$label})", 'Saldo tijd-voor-tijd (uren)', 'Verzuimmeldingen', 'Verzuimuren', 'Verzuimpercentage'];

        $data = $rows->map(fn (array $row) => [
            $row['employee']->employee_number, $row['employee']->full_name, $row['employee']->teams->pluck('name')->implode(', '),
            $row['begin'], $row['allocated'], $row['taken'], $row['planned'], $row['end'], $row['tvt_balance'],
            $row['absence_count'], $row['absence_hours'], $row['absence_percentage'],
        ]);

        if ($request->query('format') === 'csv') {
            return $this->streamCsv("verlof-verzuim-{$year}.csv", $headers, $data);
        }

        $detail = $rows->flatMap(fn (array $row) => $row['employee']->absences->map(fn ($absence) => [
            $row['employee']->full_name, $absence->type->label(), $absence->start_date, $absence->end_date, $absence->percentage, $absence->note,
        ]));

        return XlsxWriter::make()
            ->sheet("Verlof en verzuim {$year}", $headers, $data)
            ->sheet('Verzuimmeldingen', ['Medewerker', 'Soort', 'Eerste ziektedag', 'Hersteld op', 'Percentage', 'Opmerking'], $detail)
            ->download("verlof-verzuim-{$year}.xlsx");
    }

    /**
     * @return array{0: int, 1: string, 2: Collection<int, array<string, mixed>>}
     */
    private function build(Request $request): array
    {
        $year = (int) $request->query('year', now()->year);
        $unit = $request->query('unit') === 'days' ? 'days' : 'hours';
        $from = Carbon::create($year, 1, 1);
        $to = Carbon::create($year, 12, 31);
        $until = $to->gt(today()) ? today() : $to;

        $employees = Employee::query()
            ->with(['teams', 'schedules', 'ledgerEntries', 'absences' => fn ($query) => $query
                ->whereDate('start_date', '<=', $to)
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $from))])
            ->when($request->query('team_id'), fn ($query, $team) => $query->whereHas('teams', fn ($query) => $query->where('teams.id', $team)))
            ->when($request->query('status', 'active') === 'active', fn ($query) => $query->employed())
            ->where(fn ($query) => $query->whereNull('hired_at')->orWhereDate('hired_at', '<=', $to))
            ->where(fn ($query) => $query->whereNull('left_at')->orWhereDate('left_at', '>=', $from))
            ->orderBy('last_name')
            ->get();

        $rows = $employees->map(function (Employee $employee) use ($from, $to, $until, $year, $unit): array {
            $convert = fn (float $hours) => $unit === 'days' ? $employee->toDays($hours) : round($hours, 2);
            $vacation = $employee->ledgerEntries->where('kind', BalanceKind::Vacation);
            $inYear = $vacation->filter(fn ($entry) => $entry->entry_date->year === $year);
            $negative = $inYear->filter(fn ($entry) => $entry->hours < 0);

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
                'begin' => $convert((float) $vacation->filter(fn ($entry) => $entry->entry_date->lt($from))->sum('hours')),
                'allocated' => $convert((float) $inYear->filter(fn ($entry) => $entry->hours > 0)->sum('hours')),
                'taken' => $convert(-1 * (float) $negative->filter(fn ($entry) => $entry->entry_date->lte(today()))->sum('hours')),
                'planned' => $convert(-1 * (float) $negative->filter(fn ($entry) => $entry->entry_date->gt(today()))->sum('hours')),
                'end' => $convert((float) $vacation->filter(fn ($entry) => $entry->entry_date->lte($to))->sum('hours')),
                'tvt_balance' => round((float) $employee->ledgerEntries->where('kind', BalanceKind::TimeForTime)->sum('hours'), 2),
                'absence_count' => $absenceCount,
                'absence_hours' => round($absenceHours, 2),
                'scheduled' => $scheduled,
                'absence_percentage' => $scheduled > 0 ? round($absenceHours / $scheduled * 100, 1) : 0.0,
            ];
        });

        return [$year, $unit, $rows];
    }
}
