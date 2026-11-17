<?php

namespace App\Http\Controllers;

use App\Enums\BalanceKind;
use App\Enums\LedgerReason;
use App\Enums\TimeEntryStatus;
use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\Employee;
use App\Models\HourType;
use App\Models\LeaveLedgerEntry;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\TimeEntry;
use App\Services\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hour reports for the company or yourself, filterable and exportable. Rows are limited to what the user may see.
 */
class HoursReportController extends Controller
{
    use HandlesCsv;

    public function index(Request $request): View
    {
        $report = $this->build($request);

        return view('hours.report', [
            ...$report,
            'employeeOptions' => Employee::orderBy('first_name')->orderBy('last_name')->get()->pluck('full_name', 'id'),
            'projectOptions' => Project::orderBy('name')->pluck('name', 'id'),
            'serviceOptions' => ProjectService::orderBy('name')->get()->pluck('name', 'name')->unique(),
            'hourTypeOptions' => HourType::orderBy('name')->pluck('name', 'id'),
            'statusOptions' => TimeEntryStatus::options(),
            'canSeeOthers' => $request->user()->can('hours.manage') || $request->user()->can('hours.approve'),
        ]);
    }

    public function export(Request $request): StreamedResponse|BinaryFileResponse
    {
        $report = $this->build($request);
        /** @var Collection<int, TimeEntry> $entries */
        $entries = $report['entries'];

        if ($request->query('format') === 'csv') {
            return $this->streamCsv('uren-'.now()->format('Y-m-d').'.csv', ['date', 'employee', 'project_number', 'project', 'service', 'hour_type', 'start_time', 'end_time', 'hours', 'rate', 'amount', 'status', 'note'],
                $entries->map(fn (TimeEntry $e) => [$e->date->format('Y-m-d'), $e->employee->full_name, $e->project->number, $e->project->name, $e->service?->name, $e->hourType?->name, $e->start_time, $e->end_time, $e->hours, $e->rate, $e->amount(), $e->status->label(), $e->note]));
        }

        return XlsxWriter::make()
            ->sheet('Uren', ['Datum', 'Medewerker', 'Nummer', 'Project', 'Dienst', 'Urensoort', 'Start', 'Eind', 'Uren', 'Tarief', 'Bedrag', 'Status', 'Verwerkt voor facturatie', 'Omschrijving'],
                $entries->map(fn (TimeEntry $e) => [$e->date, $e->employee->full_name, $e->project->number, $e->project->name, $e->service?->name, $e->hourType?->name, $e->start_time, $e->end_time, $e->hours, $e->rate, $e->amount(), $e->status->label(), $e->invoiced_at?->format('d-m-Y'), $e->note]))
            ->sheet('Per medewerker', ['Medewerker', 'Geregistreerd', 'Goedgekeurd', 'Ingediend', 'Concept', 'Rooster', 'Verschil', 'Overuren (tijd-voor-tijd)', 'Beschikbaar', 'Productiviteit %', 'Declarabiliteit %', 'Declarabel'],
                collect($report['byEmployee'])->map(fn (array $r) => [$r['name'], $r['hours'], $r['approved'], $r['submitted'], $r['draft'], $r['scheduled'], $r['difference'], $r['overtime'], $r['available'], $r['productivity'], $r['declarability'], $r['declarable']]))
            ->sheet('Per project', ['Nummer', 'Project', 'Uren', 'Bedrag'], collect($report['byProject'])->map(fn (array $r) => [$r['number'], $r['name'], $r['hours'], $r['amount']]))
            ->sheet('Per dienst', ['Project', 'Dienst', 'Uren', 'Bedrag'], collect($report['byService'])->map(fn (array $r) => [$r['project'], $r['name'], $r['hours'], $r['amount']]))
            ->sheet('Per urensoort', ['Urensoort', 'Uren', 'Bedrag'], collect($report['byHourType'])->map(fn (array $r) => [$r['name'], $r['hours'], $r['amount']]))
            ->download('uren-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Request $request): array
    {
        $user = $request->user();
        abort_unless($user->can('hours.register') || $user->can('hours.approve') || $user->can('hours.manage'), 403);

        $filters = $request->only(['employee_id', 'project_id', 'hour_type_id', 'status', 'from', 'to']);
        $filters['from'] ??= today()->startOfMonth()->format('Y-m-d');
        $filters['to'] ??= today()->endOfMonth()->format('Y-m-d');

        if ($name = $request->query('service')) {
            $filters['service_name'] = $name;
        }

        $entries = TimeEntry::visibleTo($user)->filter($filters)
            ->when($filters['service_name'] ?? null, fn ($query, $name) => $query->whereHas('service', fn ($service) => $service->where('name', $name)))
            ->with(['employee.schedules', 'project', 'service', 'hourType'])->orderBy('date')->orderBy('id')->get();

        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->startOfDay();

        $overtime = LeaveLedgerEntry::where('kind', BalanceKind::TimeForTime->value)->where('reason', LedgerReason::ExtraHours->value)
            ->whereBetween('hours_date', [$from, $to])->selectRaw('employee_id, sum(hours) as hours')->groupBy('employee_id')->pluck('hours', 'employee_id');

        $canSeeOthers = $user->can('hours.manage') || $user->can('hours.approve');
        $roster = $canSeeOthers ? Employee::employed()->with('schedules')->get() : collect([$user->employee])->filter()->each->load('schedules');
        $employees = $roster->merge($entries->pluck('employee'))->unique('id');
        $byEntryEmployee = $entries->groupBy('employee_id');

        $byEmployee = $employees->map(function (Employee $employee) use ($byEntryEmployee, $from, $to, $overtime): array {
            $group = $byEntryEmployee->get($employee->id, collect());
            $hours = round($group->sum('hours'), 2);
            $scheduled = $employee->scheduledHours($from, $to);
            $byStatus = fn (TimeEntryStatus $status) => round($group->where('status', $status)->sum('hours'), 2);
            $declarable = $group->filter(fn (TimeEntry $entry) => $entry->project->is_invoiceable);
            $percentage = fn (float $part) => $scheduled > 0 ? round($part / $scheduled * 100, 2) : 0.0;

            return ['name' => $employee->full_name, 'hours' => $hours, 'approved' => $byStatus(TimeEntryStatus::Approved), 'submitted' => $byStatus(TimeEntryStatus::Submitted), 'draft' => $byStatus(TimeEntryStatus::Draft) + $byStatus(TimeEntryStatus::Rejected),
                'scheduled' => $scheduled, 'difference' => round($hours - $scheduled, 2), 'overtime' => round((float) ($overtime[$employee->id] ?? 0), 2),
                'available' => max(0.0, round($scheduled - $hours, 2)), 'productivity' => $percentage($hours), 'declarability' => $percentage(round($declarable->sum('hours'), 2)),
                'declarable' => round($declarable->sum(fn (TimeEntry $entry) => $entry->amount()), 2)];
        })->sortBy('name')->values()->all();

        return [
            'filters' => $filters,
            'entries' => $entries,
            'totalHours' => round($entries->sum('hours'), 2),
            'totalAmount' => round($entries->sum(fn (TimeEntry $e) => $e->amount()), 2),
            'byEmployee' => $byEmployee,
            'byProject' => $entries->groupBy('project_id')->map(fn (Collection $g) => ['number' => $g->first()->project->number, 'name' => $g->first()->project->name, 'hours' => round($g->sum('hours'), 2), 'amount' => round($g->sum(fn ($e) => $e->amount()), 2)])->sortByDesc('hours')->values()->all(),
            'byService' => $entries->groupBy('project_service_id')->map(fn (Collection $g) => ['project' => $g->first()->project->name, 'name' => $g->first()->service?->name ?? '—', 'hours' => round($g->sum('hours'), 2), 'amount' => round($g->sum(fn ($e) => $e->amount()), 2)])->sortByDesc('hours')->values()->all(),
            'byHourType' => $entries->groupBy(fn (TimeEntry $e) => $e->hour_type_id ?? 0)->map(fn (Collection $g) => ['name' => $g->first()->hourType?->name ?? 'Zonder urensoort', 'hours' => round($g->sum('hours'), 2), 'amount' => round($g->sum(fn ($e) => $e->amount()), 2)])->sortByDesc('hours')->values()->all(),
        ];
    }
}
