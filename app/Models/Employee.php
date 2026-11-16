<?php

namespace App\Models;

use App\Enums\BalanceKind;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

#[Guarded(['id'])]
class Employee extends Model
{
    use HasFactory;

    /**
     * Fields only visible to people allowed to see private data.
     *
     * @var array<int, string>
     */
    public const PRIVATE_FIELDS = [
        'private_email', 'date_of_birth', 'gender', 'street', 'zipcode', 'city', 'country', 'nationality', 'bsn', 'iban',
        'emergency_contact_name', 'emergency_contact_phone',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hired_at' => 'date',
            'left_at' => 'date',
            'bsn' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => collect([$this->first_name, $this->last_name_prefix, $this->last_name])->filter()->implode(' '));
    }

    protected function initialsLabel(): Attribute
    {
        return Attribute::get(fn (): string => mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1)));
    }

    /**
     * Employed today: flagged active, and not past the leaving date.
     */
    protected function isEmployed(): Attribute
    {
        return Attribute::get(fn (): bool => $this->is_active && ($this->left_at === null || $this->left_at->gte(today())));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class)->orderByDesc('start_date');
    }

    public function currentContract(): HasOne
    {
        return $this->hasOne(EmploymentContract::class)
            ->whereDate('start_date', '<=', today())
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->latest('start_date');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class)->orderByDesc('effective_from');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(EmployeeRate::class)->orderByDesc('effective_from');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class)->orderByDesc('start_date');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LeaveLedgerEntry::class)->orderByDesc('entry_date')->orderByDesc('id');
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class)->orderByDesc('start_date');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->latest();
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable')->latest();
    }

    public function balance(BalanceKind $kind): float
    {
        return round((float) $this->ledgerEntries()->getQuery()->reorder()->where('kind', $kind->value)->sum('hours'), 2);
    }

    public function scheduleOn(Carbon $date): ?WorkSchedule
    {
        return $this->schedules->first(fn (WorkSchedule $schedule) => $schedule->effective_from->lte($date));
    }

    public function rateOn(Carbon $date): ?EmployeeRate
    {
        return $this->rates->first(fn (EmployeeRate $rate) => $rate->effective_from->lte($date));
    }

    /**
     * Scheduled working hours between two dates (inclusive), following schedule changes by effective date.
     */
    public function scheduledHours(Carbon $from, Carbon $to): float
    {
        $hours = 0.0;

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $hours += $this->scheduleOn($day)?->hoursOn($day) ?? 0.0;
        }

        return round($hours, 2);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $terms = preg_split('/\s+/', trim((string) ($filters['q'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        $status = $filters['status'] ?? 'active';

        foreach ($terms as $term) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(fn (Builder $query) => $query->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('last_name_prefix', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('employee_number', 'like', $like)
                ->orWhere('job_title', 'like', $like));
        }

        return $query
            ->when(Arr::wrap($filters['team_id'] ?? []), fn (Builder $query, array $ids) => $query->whereIn('team_id', $ids))
            ->when(Arr::wrap($filters['discipline_id'] ?? []), fn (Builder $query, array $ids) => $query->whereIn('discipline_id', $ids))
            ->when(Arr::wrap($filters['manager_id'] ?? []), fn (Builder $query, array $ids) => $query->whereIn('manager_id', $ids))
            ->when(Arr::wrap($filters['contract_type'] ?? []), fn (Builder $query, array $types) => $query->whereHas(
                'currentContract', fn (Builder $query) => $query->whereIn('contract_type', $types)
            ))
            ->when($status === 'active', fn (Builder $query) => $query->employed())
            ->when($status === 'left', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('is_active', false)->orWhereDate('left_at', '<', today())))
            ->when($status === 'upcoming', fn (Builder $query) => $query->whereDate('hired_at', '>', today()));
    }

    public function scopeEmployed(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('left_at')->orWhereDate('left_at', '>=', today()));
    }
}
