<?php

namespace App\Models;

use App\Enums\TimeEntryStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registered hours of an employee on a project service.
 * Concept and rejected entries belong to the employee; submitted and approved entries are only changed by authorised users.
 */
#[Guarded(['id'])]
class TimeEntry extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hours' => 'float',
            'rate' => 'float',
            'status' => TimeEntryStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'invoiced_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ProjectService::class, 'project_service_id');
    }

    public function hourType(): BelongsTo
    {
        return $this->belongsTo(HourType::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function amount(): float
    {
        return round($this->hours * $this->rate, 2);
    }

    public function isLocked(): bool
    {
        return $this->invoiced_at !== null;
    }

    public function isOwnedBy(User $user): bool
    {
        return $user->employee !== null && $user->employee->id === $this->employee_id;
    }

    /**
     * Employees change their own concept and rejected hours; managers also correct submitted and approved hours.
     */
    public function canBeEditedBy(User $user): bool
    {
        if ($user->can('hours.manage')) {
            return true;
        }

        return $this->isOwnedBy($user)
            && ! $this->isLocked()
            && in_array($this->status, [TimeEntryStatus::Draft, TimeEntryStatus::Rejected], true)
            && ($user->can('hours.register') || $user->can('projects.view'));
    }

    /**
     * Approvers and the project manager review hours of others; only managers review their own.
     */
    public function canBeReviewedBy(User $user): bool
    {
        if ($user->can('hours.manage')) {
            return true;
        }

        if ($this->isOwnedBy($user)) {
            return false;
        }

        return $user->can('hours.approve') || ($user->employee !== null && $this->project?->manager_id === $user->employee->id);
    }

    /**
     * Everybody sees their own hours; approvers and managers see all; project managers also see the hours on their projects.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('hours.manage') || $user->can('hours.approve')) {
            return $query;
        }

        $employeeId = $user->employee?->id ?? 0;

        return $query->where(fn (Builder $query) => $query->where('employee_id', $employeeId)
            ->orWhereHas('project', fn (Builder $project) => $project->where('manager_id', $employeeId)));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['employee_id'] ?? null, fn (Builder $query, $id) => $query->where('employee_id', $id))
            ->when($filters['project_id'] ?? null, fn (Builder $query, $id) => $query->where('project_id', $id))
            ->when($filters['project_service_id'] ?? null, fn (Builder $query, $id) => $query->where('project_service_id', $id))
            ->when($filters['hour_type_id'] ?? null, fn (Builder $query, $id) => $query->where('hour_type_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date))
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where('note', 'like', "%{$term}%"));
    }

    /**
     * The data the entry dialog needs to show and change this entry.
     *
     * @return array<string, mixed>
     */
    public function dialogPayload(User $user): array
    {
        return [
            'id' => $this->id, 'employee_id' => $this->employee_id, 'date' => $this->date->format('Y-m-d'), 'project_id' => $this->project_id,
            'project_service_id' => $this->project_service_id, 'hour_type_id' => $this->hour_type_id, 'start_time' => $this->start_time, 'end_time' => $this->end_time,
            'hours' => $this->hours, 'note' => $this->note, 'status' => $this->status->label(), 'reject_reason' => $this->reject_reason,
            'editable' => $this->canBeEditedBy($user), 'locked' => $this->isLocked(),
            'urls' => ['update' => route('hours.update', $this), 'duplicate' => route('hours.duplicate', $this), 'split' => route('hours.split', $this), 'destroy' => route('hours.destroy', $this)],
        ];
    }
}
