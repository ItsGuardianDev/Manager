<?php

namespace App\Http\Controllers;

use App\Enums\BalanceKind;
use App\Enums\TimeEntryStatus;
use App\Models\Employee;
use App\Models\HourType;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\TimeEntry;
use App\Services\TimeRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HoursController extends Controller
{
    private const FILTERS = ['employee_id', 'project_id', 'project_service_id', 'hour_type_id', 'status', 'from', 'to', 'q'];

    public function __construct(private TimeRegistrar $registrar) {}

    public function index(Request $request): View
    {
        return $this->listing($request, 'overview');
    }

    public function approvals(Request $request): View
    {
        return $this->listing($request, 'approvals');
    }

    private function listing(Request $request, string $mode): View
    {
        $this->authorizeModule($request);
        $user = $request->user();

        $filters = $request->only(self::FILTERS);

        if ($mode === 'approvals') {
            $filters['status'] = $request->query('status', TimeEntryStatus::Submitted->value);
        }

        $query = TimeEntry::visibleTo($user)->filter($filters)
            ->when($mode === 'approvals' && ! $user->can('hours.manage'), fn ($query) => $query->where('employee_id', '!=', $user->employee?->id ?? 0));

        $stats = (clone $query)->reorder()->selectRaw('status, sum(hours) as hours, count(*) as entries')->groupBy('status')->get()->keyBy(fn ($row) => $row->status instanceof TimeEntryStatus ? $row->status->value : $row->status);

        $entries = $query->with(['employee', 'project', 'service', 'hourType'])->orderByDesc('date')->orderByDesc('id')->paginate(40)->withQueryString();

        return view('hours.index', [
            'mode' => $mode,
            'entries' => $entries,
            'filters' => $filters,
            'stats' => $stats,
            'totalHours' => round((float) $stats->sum('hours'), 2),
            'canSeeOthers' => $user->can('hours.manage') || $user->can('hours.approve'),
            'employeeOptions' => Employee::orderBy('first_name')->orderBy('last_name')->get()->pluck('full_name', 'id'),
            'projectOptions' => Project::orderBy('name')->pluck('name', 'id'),
            'hourTypeOptions' => HourType::orderBy('name')->pluck('name', 'id'),
            'statusOptions' => TimeEntryStatus::options(),
            ...$this->dialogData($request, $user->employee),
        ]);
    }

    public function week(Request $request): View
    {
        $employee = $this->targetEmployee($request);
        $monday = $this->weekStart($request->query('date'));
        $days = collect(range(0, 6))->map(fn (int $i) => $monday->copy()->addDays($i));

        $entries = TimeEntry::where('employee_id', $employee->id)->whereBetween('date', [$monday, $monday->copy()->addDays(6)])
            ->with(['project', 'service.hourLines', 'service.timeEntries', 'hourType'])->orderBy('id')->get();

        $rows = $entries->groupBy(fn (TimeEntry $entry) => $entry->project_id.'-'.$entry->project_service_id.'-'.($entry->hour_type_id ?? 0))
            ->map(fn (Collection $group) => ['first' => $group->first(), 'cells' => $group->groupBy(fn (TimeEntry $entry) => $entry->date->format('Y-m-d'))])
            ->values();

        return view('hours.week', [
            'employee' => $employee,
            'monday' => $monday,
            'days' => $days,
            'rows' => $rows,
            'scheduled' => $days->mapWithKeys(fn (Carbon $day) => [$day->format('Y-m-d') => $employee->load('schedules')->scheduleOn($day)?->hoursOn($day) ?? 0.0]),
            'canSubmit' => $entries->contains(fn (TimeEntry $entry) => in_array($entry->status, [TimeEntryStatus::Draft, TimeEntryStatus::Rejected], true) && ! $entry->isLocked()),
            'balance' => $employee->balance(BalanceKind::TimeForTime),
            'canPickEmployee' => $request->user()->can('hours.manage'),
            'employeeOptions' => Employee::employed()->orderBy('first_name')->orderBy('last_name')->get()->pluck('full_name', 'id'),
            ...$this->dialogData($request, $employee),
        ]);
    }

    public function calendar(Request $request): View
    {
        $employee = $this->targetEmployee($request);
        $view = $request->query('view') === 'day' ? 'day' : 'week';
        $anchor = Carbon::parse($request->query('date', today()))->startOfDay();
        $start = $view === 'day' ? $anchor : $anchor->copy()->startOfWeek();
        $days = collect(range(0, $view === 'day' ? 0 : 6))->map(fn (int $i) => $start->copy()->addDays($i));

        $entries = TimeEntry::where('employee_id', $employee->id)->whereBetween('date', [$days->first(), $days->last()])
            ->with(['project', 'service', 'hourType'])->orderBy('start_time')->orderBy('id')->get()->groupBy(fn (TimeEntry $entry) => $entry->date->format('Y-m-d'));

        return view('hours.calendar', [
            'employee' => $employee,
            'view' => $view,
            'anchor' => $anchor,
            'days' => $days,
            'entries' => $entries,
            'scheduled' => $days->mapWithKeys(fn (Carbon $day) => [$day->format('Y-m-d') => $employee->load('schedules')->scheduleOn($day)?->hoursOn($day) ?? 0.0]),
            'canPickEmployee' => $request->user()->can('hours.manage'),
            'employeeOptions' => Employee::employed()->orderBy('first_name')->orderBy('last_name')->get()->pluck('full_name', 'id'),
            ...$this->dialogData($request, $employee),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeModule($request);

        $entry = $this->registrar->create($request->user(), $this->entryData($request));

        return back()->with('status', "Uren geregistreerd op {$entry->project->name}.");
    }

    public function update(Request $request, TimeEntry $entry): RedirectResponse
    {
        $this->authorizeVisible($request, $entry);

        $this->registrar->update($request->user(), $entry, $this->entryData($request, $entry), $request->input('correction_reason'));

        return back()->with('status', 'Registratie opgeslagen.');
    }

    public function destroy(Request $request, TimeEntry $entry): RedirectResponse
    {
        $this->authorizeVisible($request, $entry);

        $this->registrar->delete($request->user(), $entry, $request->input('correction_reason'));

        return back()->with('status', 'Registratie verwijderd.');
    }

    public function duplicate(Request $request, TimeEntry $entry): RedirectResponse
    {
        $this->authorizeVisible($request, $entry);

        $data = $request->validate(['date' => ['nullable', 'date']]);

        $this->registrar->duplicate($request->user(), $entry, isset($data['date']) ? Carbon::parse($data['date']) : null);

        return back()->with('status', 'Registratie gedupliceerd.');
    }

    public function split(Request $request, TimeEntry $entry): RedirectResponse
    {
        $this->authorizeVisible($request, $entry);

        $data = $request->validate([
            'split_hours' => ['required', 'numeric', 'gt:0'],
            'project_service_id' => ['required', 'exists:project_services,id'],
            'hour_type_id' => ['nullable', 'exists:hour_types,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['split_hours' => 'af te splitsen uren']);

        $this->registrar->split($request->user(), $entry, (float) $data['split_hours'], $data);

        return back()->with('status', 'Registratie gesplitst.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $this->authorizeModule($request);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:submit,approve,reject,invoice'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], ['ids.required' => 'Selecteer eerst een of meer registraties.'], ['reason' => 'reden']);

        $user = $request->user();
        $entries = $this->registrar->entriesFor($user, $data['ids']);

        if ($data['action'] === 'reject' && trim((string) ($data['reason'] ?? '')) === '') {
            throw ValidationException::withMessages(['reason' => 'Geef een reden op voor het afkeuren.']);
        }

        $count = match ($data['action']) {
            'submit' => $this->registrar->submit($user, $entries),
            'approve' => $this->registrar->approve($user, $entries),
            'reject' => $this->registrar->reject($user, $entries, $data['reason']),
            'invoice' => $this->registrar->markInvoiced($user, $entries),
        };

        $verb = ['submit' => 'ingediend', 'approve' => 'goedgekeurd', 'reject' => 'afgekeurd', 'invoice' => 'verwerkt voor facturatie'][$data['action']];

        return back()->with('status', $count === 0 ? 'Er is niets gewijzigd: de selectie komt niet in aanmerking.' : "{$count} registraties {$verb}.");
    }

    public function saveWeek(Request $request): RedirectResponse
    {
        $employee = $this->targetEmployee($request, input: true);
        $monday = $this->weekStart($request->input('date'));
        $user = $request->user();

        $request->validate([
            'rows' => ['nullable', 'array'],
            'rows.*.project_id' => ['nullable', 'exists:projects,id'],
            'rows.*.project_service_id' => ['nullable', 'exists:project_services,id'],
            'rows.*.hour_type_id' => ['nullable', 'exists:hour_types,id'],
            'rows.*.hours' => ['nullable', 'array'],
            'rows.*.hours.*' => ['nullable', 'numeric', 'min:0', 'max:24'],
        ], [], ['rows.*.hours.*' => 'uren']);

        try {
            DB::transaction(function () use ($request, $employee, $monday, $user): void {
                foreach ((array) $request->input('rows', []) as $row) {
                    if (empty($row['project_id']) || empty($row['project_service_id'])) {
                        continue;
                    }

                    foreach ((array) ($row['hours'] ?? []) as $date => $value) {
                        $day = Carbon::parse($date)->startOfDay();

                        if ($day->lt($monday) || $day->gt($monday->copy()->addDays(6))) {
                            continue;
                        }

                        $this->saveCell($user, $employee, $row, $day, $value === null || $value === '' ? 0.0 : (float) $value);
                    }
                }
            });
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return back()->with('status', 'Weekstaat opgeslagen.');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function saveCell($user, Employee $employee, array $row, Carbon $day, float $hours): void
    {
        $existing = TimeEntry::where(['employee_id' => $employee->id, 'project_id' => $row['project_id'], 'project_service_id' => $row['project_service_id']])
            ->where('hour_type_id', ($row['hour_type_id'] ?? null) ?: null)->whereDate('date', $day)->get();

        if ($existing->count() > 1) {
            return;
        }

        $entry = $existing->first();

        if (! $entry) {
            if ($hours > 0) {
                $this->registrar->create($user, ['employee_id' => $employee->id, 'project_id' => $row['project_id'], 'project_service_id' => $row['project_service_id'], 'hour_type_id' => $row['hour_type_id'] ?? null, 'date' => $day->format('Y-m-d'), 'hours' => $hours]);
            }

            return;
        }

        if (! $entry->canBeEditedBy($user) || $entry->isLocked() || abs($entry->hours - $hours) < 0.001) {
            return;
        }

        if ($hours <= 0) {
            $this->registrar->delete($user, $entry);

            return;
        }

        $this->registrar->update($user, $entry, ['hours' => $hours, 'start_time' => null, 'end_time' => null]);
    }

    public function submitWeek(Request $request): RedirectResponse
    {
        $employee = $this->targetEmployee($request, input: true);
        $monday = $this->weekStart($request->input('date'));

        $entries = TimeEntry::where('employee_id', $employee->id)->whereBetween('date', [$monday, $monday->copy()->addDays(6)])->with(['employee', 'project'])->get();
        $count = $this->registrar->submit($request->user(), $entries);

        return back()->with('status', $count === 0 ? 'Er zijn geen concepturen om in te dienen.' : "{$count} registraties ingediend ter goedkeuring.");
    }

    public function copyWeek(Request $request): RedirectResponse
    {
        $employee = $this->targetEmployee($request, input: true);
        $data = $request->validate(['date' => ['required', 'date'], 'target' => ['required', 'date']], [], ['target' => 'doelweek']);

        $from = $this->weekStart($data['date']);
        $to = $this->weekStart($data['target']);

        if ($from->equalTo($to)) {
            return back()->withErrors(['target' => 'Kies een andere week om naar te kopiëren.']);
        }

        $offset = (int) $from->diffInDays($to, false);
        $copied = 0;
        $skipped = 0;

        foreach (TimeEntry::where('employee_id', $employee->id)->whereBetween('date', [$from, $from->copy()->addDays(6)])->get() as $entry) {
            $date = $entry->date->copy()->addDays($offset);
            $exists = TimeEntry::where(['employee_id' => $employee->id, 'project_id' => $entry->project_id, 'project_service_id' => $entry->project_service_id])
                ->where('hour_type_id', $entry->hour_type_id)->whereDate('date', $date)->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            try {
                $this->registrar->duplicate($request->user(), $entry, $date);
                $copied++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        return redirect()->route('hours.week', ['date' => $to->format('Y-m-d'), 'employee_id' => $employee->id])->with('status', "{$copied} registraties gekopieerd".($skipped ? ", {$skipped} overgeslagen (bestond al of mag niet meer)." : '.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function entryData(Request $request, ?TimeEntry $entry = null): array
    {
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'project_id' => ['required', 'exists:projects,id'],
            'project_service_id' => ['required', 'exists:project_services,id'],
            'hour_type_id' => ['nullable', 'exists:hour_types,id'],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'string', 'max:5'],
            'end_time' => ['nullable', 'string', 'max:5'],
            'hours' => ['nullable', 'numeric', 'gt:0', 'max:24'],
            'note' => ['nullable', 'string', 'max:255'],
            'correction_reason' => ['nullable', 'string', 'max:255'],
        ], [], ['project_id' => 'project', 'project_service_id' => 'dienst', 'hour_type_id' => 'urensoort', 'date' => 'datum', 'start_time' => 'starttijd', 'end_time' => 'eindtijd', 'hours' => 'uren']);

        $user = $request->user();

        $data['employee_id'] = $user->can('hours.manage') || $user->can('projects.edit')
            ? ($data['employee_id'] ?? $entry?->employee_id ?? $user->employee?->id)
            : ($entry?->employee_id ?? $user->employee?->id);

        abort_if($data['employee_id'] === null, 403, 'Er is geen medewerker aan jouw account gekoppeld.');

        return collect($data)->except('correction_reason')->all();
    }

    /**
     * The employee a personal view (week or calendar) is about: yourself, or anyone for managers.
     */
    private function targetEmployee(Request $request, bool $input = false): Employee
    {
        $this->authorizeModule($request);
        $user = $request->user();
        $requested = $input ? $request->input('employee_id') : $request->query('employee_id');

        $employee = $requested && $user->can('hours.manage') ? Employee::findOrFail($requested) : $user->employee;

        abort_if($employee === null, 403, 'Er is geen medewerker aan jouw account gekoppeld.');

        return $employee;
    }

    /**
     * Projects and services hours can be booked on, for the entry dialog and the timesheet.
     *
     * @return array<string, mixed>
     */
    private function dialogData(Request $request, ?Employee $employee): array
    {
        $user = $request->user();

        $services = ProjectService::query()
            ->where('is_active', true)->where('allow_hours', true)
            ->whereHas('project', fn ($query) => $query->where('status', 'active'))
            ->with('project')->withSum('hourLines as budget_hours', 'hours')->withSum('timeEntries as spent_hours', 'hours')
            ->orderBy('sort_order')->get()
            ->filter(fn (ProjectService $service) => $user->can('hours.manage') || $user->can('projects.edit') || ! $employee || $service->project->allowsEmployee($employee))
            ->sortBy(fn (ProjectService $service) => mb_strtolower($service->project->name))->values();

        return [
            'services' => $services,
            'projects' => $services->pluck('project')->unique('id')->sortBy('name')->values(),
            'hourTypes' => HourType::orderBy('name')->get(),
            'dialogEmployees' => $user->can('hours.manage') || $user->can('projects.edit') ? Employee::employed()->orderBy('first_name')->orderBy('last_name')->get()->pluck('full_name', 'id') : null,
            'defaultEmployeeId' => $employee?->id,
        ];
    }

    private function weekStart(?string $date): Carbon
    {
        return Carbon::parse($date ?: today())->startOfWeek();
    }

    private function authorizeModule(Request $request): void
    {
        $user = $request->user();

        abort_unless($user->can('hours.register') || $user->can('hours.approve') || $user->can('hours.manage'), 403);
    }

    private function authorizeVisible(Request $request, TimeEntry $entry): void
    {
        $this->authorizeModule($request);

        abort_unless(TimeEntry::visibleTo($request->user())->whereKey($entry->id)->exists(), 403);
    }
}
