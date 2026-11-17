<?php

namespace App\Services;

use App\Enums\BalanceKind;
use App\Enums\LedgerReason;
use App\Enums\TimeEntryStatus;
use App\Models\Employee;
use App\Models\HourType;
use App\Models\LeaveLedgerEntry;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * All rules for registering, changing, submitting and approving hours. Controllers only translate requests.
 */
class TimeRegistrar
{
    public function __construct(private BudgetMonitor $monitor) {}

    public function mayRegisterFor(User $actor, Employee $employee): bool
    {
        if ($actor->can('hours.manage') || $actor->can('projects.edit')) {
            return true;
        }

        return $actor->employee?->is($employee) === true && ($actor->can('hours.register') || $actor->can('projects.view'));
    }

    /**
     * @param  array<string, mixed>  $data  employee_id, project_id, project_service_id, hour_type_id, date, start_time, end_time, hours, note
     */
    public function create(User $actor, array $data): TimeEntry
    {
        $entry = TimeEntry::create([...$this->resolve($actor, $data), 'status' => TimeEntryStatus::Draft]);
        $this->monitor->check($entry->project);

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, TimeEntry $entry, array $data, ?string $reason = null): TimeEntry
    {
        $this->authorizeChange($actor, $entry, $reason);

        $originalDate = $entry->date->copy();
        $originalEmployee = $entry->employee;
        $originalProject = $entry->project;
        $attributes = $this->resolve($actor, [...$entry->only(['employee_id', 'project_id', 'project_service_id', 'hour_type_id', 'start_time', 'end_time', 'note']), 'date' => $entry->date->format('Y-m-d'), 'hours' => null, ...$data], $entry);

        $entry->fill($attributes);

        if ($entry->status === TimeEntryStatus::Rejected && $entry->isOwnedBy($actor)) {
            $entry->status = TimeEntryStatus::Draft;
            $entry->reject_reason = null;
        }

        $entry->save();

        $this->monitor->check($entry->project);

        if ($originalProject->id !== $entry->project_id) {
            $this->monitor->check($originalProject->fresh());
        }

        $this->syncOvertime($entry->employee, $entry->date);
        $this->syncOvertime($originalEmployee, $originalDate);

        if ($entry->status !== TimeEntryStatus::Draft || $reason) {
            $entry->project->logEvent("Uren van {$entry->employee->full_name} op {$entry->date->format('d-m-Y')} gecorrigeerd door {$actor->name}".($reason ? ": {$reason}" : '').'.');
        }

        return $entry;
    }

    public function delete(User $actor, TimeEntry $entry, ?string $reason = null): void
    {
        $this->authorizeChange($actor, $entry, $reason);

        $project = $entry->project;
        $employee = $entry->employee;
        $date = $entry->date;
        $wasPlain = $entry->status === TimeEntryStatus::Draft && ! $reason;
        $label = "{$entry->hours} u van {$employee->full_name} op {$date->format('d-m-Y')}";

        $entry->delete();

        $this->monitor->check($project);
        $this->syncOvertime($employee, $date);

        if (! $wasPlain) {
            $project->logEvent("Uren verwijderd ({$label}) door {$actor->name}".($reason ? ": {$reason}" : '').'.');
        }
    }

    public function duplicate(User $actor, TimeEntry $entry, ?Carbon $date = null): TimeEntry
    {
        return $this->create($actor, [
            ...$entry->only(['employee_id', 'project_id', 'project_service_id', 'hour_type_id', 'start_time', 'end_time', 'hours', 'note']),
            'date' => ($date ?? $entry->date)->format('Y-m-d'),
        ]);
    }

    /**
     * Moves part of the hours to another service or hour type on the same day.
     *
     * @param  array<string, mixed>  $target  project_service_id, hour_type_id, note
     */
    public function split(User $actor, TimeEntry $entry, float $hours, array $target): TimeEntry
    {
        $this->authorizeChange($actor, $entry, null);

        if ($hours <= 0 || $hours >= $entry->hours) {
            throw ValidationException::withMessages(['split_hours' => 'Het af te splitsen aantal uren moet groter dan 0 en kleiner dan de oorspronkelijke registratie zijn.']);
        }

        $remaining = round($entry->hours - $hours, 2);
        $part = $this->resolve($actor, [...$entry->only(['employee_id', 'project_id']), 'project_service_id' => $target['project_service_id'] ?? $entry->project_service_id, 'hour_type_id' => $target['hour_type_id'] ?? $entry->hour_type_id, 'date' => $entry->date->format('Y-m-d'), 'hours' => $hours, 'note' => $target['note'] ?? $entry->note], null, ignoreDayLimit: true);

        $entry->update(['hours' => $remaining, 'end_time' => $entry->start_time ? $this->addHours($entry->start_time, $remaining) : null]);
        $new = TimeEntry::create([...$part, 'status' => $entry->status, 'submitted_at' => $entry->submitted_at, 'reviewed_by' => $entry->reviewed_by, 'reviewed_at' => $entry->reviewed_at]);

        $this->monitor->check($entry->project);

        return $new;
    }

    /**
     * @param  iterable<TimeEntry>  $entries
     */
    public function submit(User $actor, iterable $entries): int
    {
        $count = 0;

        foreach ($entries as $entry) {
            if ($entry->isLocked() || ! in_array($entry->status, [TimeEntryStatus::Draft, TimeEntryStatus::Rejected], true) || ! ($entry->isOwnedBy($actor) || $actor->can('hours.manage'))) {
                continue;
            }

            $entry->update(['status' => TimeEntryStatus::Submitted, 'submitted_at' => now(), 'reject_reason' => null]);
            $count++;
        }

        return $count;
    }

    /**
     * @param  iterable<TimeEntry>  $entries
     */
    public function approve(User $actor, iterable $entries): int
    {
        $count = 0;

        foreach ($entries as $entry) {
            if ($entry->status !== TimeEntryStatus::Submitted || ! $entry->canBeReviewedBy($actor)) {
                continue;
            }

            $entry->update(['status' => TimeEntryStatus::Approved, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'reject_reason' => null]);
            $this->syncOvertime($entry->employee, $entry->date);
            $count++;
        }

        return $count;
    }

    /**
     * @param  iterable<TimeEntry>  $entries
     */
    public function reject(User $actor, iterable $entries, string $reason): int
    {
        $count = 0;

        foreach ($entries as $entry) {
            if (! in_array($entry->status, [TimeEntryStatus::Submitted, TimeEntryStatus::Approved], true) || $entry->isLocked() || ! $entry->canBeReviewedBy($actor)) {
                continue;
            }

            $entry->update(['status' => TimeEntryStatus::Rejected, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'reject_reason' => $reason]);
            $this->syncOvertime($entry->employee, $entry->date);
            $count++;
        }

        return $count;
    }

    /**
     * Marks approved hours as processed for invoicing, after which they only change through a correction.
     *
     * @param  iterable<TimeEntry>  $entries
     */
    public function markInvoiced(User $actor, iterable $entries): int
    {
        abort_unless($actor->can('hours.manage'), 403);

        $count = 0;

        foreach ($entries as $entry) {
            if ($entry->status === TimeEntryStatus::Approved && ! $entry->isLocked()) {
                $entry->update(['invoiced_at' => now()]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Approved hours beyond the schedule of a day are overtime and earn time-for-time hours.
     */
    public function syncOvertime(Employee $employee, Carbon $date): void
    {
        $day = $date->copy()->startOfDay();

        LeaveLedgerEntry::where('employee_id', $employee->id)->where('reason', LedgerReason::ExtraHours->value)->whereDate('hours_date', $day)->delete();

        $approved = (float) TimeEntry::where('employee_id', $employee->id)->whereDate('date', $day)->where('status', TimeEntryStatus::Approved->value)->sum('hours');
        $scheduled = $employee->load('schedules')->scheduleOn($day)?->hoursOn($day) ?? 0.0;
        $overtime = round($approved - $scheduled, 2);

        if ($overtime > 0) {
            LeaveLedgerEntry::create([
                'employee_id' => $employee->id, 'kind' => BalanceKind::TimeForTime, 'reason' => LedgerReason::ExtraHours, 'hours' => $overtime,
                'entry_date' => $day, 'hours_date' => $day, 'description' => 'Overuren '.$day->format('d-m-Y').' (goedgekeurde uren)',
            ]);
        }
    }

    private function authorizeChange(User $actor, TimeEntry $entry, ?string $reason): void
    {
        abort_unless($entry->canBeEditedBy($actor), 403);

        if ($entry->isLocked() && trim((string) $reason) === '') {
            throw ValidationException::withMessages(['correction_reason' => 'Deze uren zijn al verwerkt voor facturatie. Geef een reden op voor de correctie.']);
        }
    }

    /**
     * Validates the combination and turns it into entry attributes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolve(User $actor, array $data, ?TimeEntry $existing = null, bool $ignoreDayLimit = false): array
    {
        $employee = Employee::findOrFail($data['employee_id']);
        abort_unless($this->mayRegisterFor($actor, $employee), 403);

        $project = Project::findOrFail($data['project_id']);
        $service = ProjectService::where('project_id', $project->id)->find($data['project_service_id'] ?? null);

        if (! $service) {
            throw ValidationException::withMessages(['project_service_id' => 'Kies een dienst van dit project.']);
        }

        $unchanged = $existing && $existing->project_service_id === $service->id;

        if (! $unchanged) {
            if ($project->status->value !== 'active') {
                throw ValidationException::withMessages(['hours' => 'Er kunnen geen uren meer worden geregistreerd op een afgerond of gesloten project.']);
            }

            if (! $service->is_active || ! $service->allow_hours) {
                throw ValidationException::withMessages(['project_service_id' => 'Op deze dienst kunnen geen uren worden geboekt.']);
            }

            if (! $project->allowsEmployee($employee)) {
                throw ValidationException::withMessages(['employee_id' => 'Deze medewerker is niet toegewezen aan dit project.']);
            }
        }

        $start = $this->minutes($data['start_time'] ?? null, 'start_time');
        $end = $this->minutes($data['end_time'] ?? null, 'end_time');
        $hours = ($data['hours'] ?? '') !== '' && $data['hours'] !== null ? round((float) $data['hours'], 2) : null;

        if ($start !== null && $end !== null) {
            if ($end <= $start) {
                throw ValidationException::withMessages(['end_time' => 'De eindtijd moet na de starttijd liggen.']);
            }

            $hours ??= round(($end - $start) / 60, 2);
        } elseif ($start !== null && $hours !== null && $start + $hours * 60 < 1440) {
            $end = (int) round($start + $hours * 60);
        }

        if ($hours === null || $hours <= 0) {
            throw ValidationException::withMessages(['hours' => 'Vul de uren of een start- en eindtijd in.']);
        }

        $date = Carbon::parse($data['date'])->startOfDay();
        $others = (float) TimeEntry::where('employee_id', $employee->id)->whereDate('date', $date)->when($existing, fn ($query) => $query->whereKeyNot($existing->id))->sum('hours');

        if (! $ignoreDayLimit && ($hours > 24 || $others + $hours > 24)) {
            throw ValidationException::withMessages(['hours' => 'Een dag heeft maximaal 24 uur.']);
        }

        $hourTypeId = ($data['hour_type_id'] ?? null) ?: null;
        $rate = $hourTypeId
            ? ($service->hourLines()->where('hour_type_id', $hourTypeId)->value('rate') ?? HourType::find($hourTypeId)?->default_rate ?? 0)
            : 0;

        return [
            'employee_id' => $employee->id, 'project_id' => $project->id, 'project_service_id' => $service->id, 'hour_type_id' => $hourTypeId,
            'date' => $date, 'start_time' => $start !== null ? $this->format($start) : null, 'end_time' => $end !== null ? $this->format($end) : null,
            'hours' => $hours, 'rate' => $rate, 'note' => ($data['note'] ?? null) ?: null,
        ];
    }

    private function minutes(?string $time, string $field): ?int
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', trim($time), $match)) {
            throw ValidationException::withMessages([$field => 'Gebruik de notatie uu:mm.']);
        }

        return (int) $match[1] * 60 + (int) $match[2];
    }

    private function format(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function addHours(string $time, float $hours): ?string
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        $total = (int) round($h * 60 + $m + $hours * 60);

        return $total < 1440 ? $this->format($total) : null;
    }

    /**
     * @return Collection<int, TimeEntry>
     */
    public function entriesFor(User $actor, array $ids): Collection
    {
        return TimeEntry::visibleTo($actor)->with(['employee', 'project'])->whereIn('id', $ids)->get();
    }
}
