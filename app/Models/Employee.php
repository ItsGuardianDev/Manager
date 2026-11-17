<?php

namespace App\Models;

use App\Enums\BalanceKind;
use App\Enums\EmployeeType;
use App\Enums\LedgerReason;
use App\Models\Concerns\HasTimeline;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

#[Guarded(['id'])]
class Employee extends Model
{
    use HasFactory, HasTimeline;

    /**
     * @var array<string, string>
     */
    public const MARITAL_STATUSES = [
        'single' => 'Ongehuwd', 'married' => 'Gehuwd', 'registered' => 'Geregistreerd partnerschap',
        'cohabiting' => 'Samenwonend', 'divorced' => 'Gescheiden', 'widowed' => 'Weduwe/weduwnaar',
    ];

    /**
     * Fields only visible to people allowed to see private data.
     *
     * @var array<int, string>
     */
    public const PRIVATE_FIELDS = [
        'private_email', 'date_of_birth', 'gender', 'street', 'zipcode', 'city', 'country', 'nationality', 'bsn', 'iban',
        'emergency_contact_name', 'emergency_contact_phone', 'marital_status', 'private_phone',
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
            'employee_type' => EmployeeType::class,
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

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->orderBy('name');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function disciplines(): BelongsToMany
    {
        return $this->belongsToMany(Discipline::class)->orderBy('name');
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

    public function balance(BalanceKind $kind): float
    {
        return round((float) $this->ledgerEntries()->getQuery()->reorder()->where('kind', $kind->value)->sum('hours'), 2);
    }

    public function scheduleOn(Carbon $date): ?WorkSchedule
    {
        return $this->schedules->first(fn (WorkSchedule $schedule) => $schedule->effective_from->lte($date));
    }

    /**
     * Balance as it stands on a date (entries dated later are still planned).
     */
    public function balanceAsOf(BalanceKind $kind, Carbon $date): float
    {
        return round((float) $this->ledgerEntries->where('kind', $kind)->filter(fn ($entry) => $entry->entry_date->lte($date))->sum('hours'), 2);
    }

    /**
     * Hours of approved leave that still lie in the future.
     */
    public function plannedHours(BalanceKind $kind): float
    {
        return round(-1 * (float) $this->ledgerEntries->where('kind', $kind)->where('reason', LedgerReason::Taken)
            ->filter(fn ($entry) => $entry->entry_date->gt(today()))->sum('hours'), 2);
    }

    /**
     * Average hours on a working day of the current schedule, used to express hours as days.
     */
    public function hoursPerDay(): float
    {
        $schedule = $this->scheduleOn(today());
        $days = $schedule ? count($schedule->pattern['odd'] ?? []) : 0;

        return $schedule && $days > 0 ? max(1.0, $schedule->weeklyHours('odd') / $days) : 8.0;
    }

    public function toDays(float $hours): float
    {
        return round($hours / $this->hoursPerDay(), 2);
    }

    /**
     * Sick hours and sickness percentage in a calendar year.
     *
     * @return array{hours: float, percentage: float}
     */
    public function absenceStats(int $year): array
    {
        $from = Carbon::create($year, 1, 1);
        $until = Carbon::create($year, 12, 31)->min(today());
        $hours = 0.0;

        foreach ($this->absences as $absence) {
            $start = $absence->start_date->max($from);
            $end = ($absence->end_date ?? today())->min($until);

            if ($start->lte($end)) {
                $hours += $this->scheduledHours($start, $end) * $absence->percentage / 100;
            }
        }

        $scheduled = $from->lte($until) ? $this->scheduledHours($from, $until) : 0.0;

        return ['hours' => round($hours, 2), 'percentage' => $scheduled > 0 ? round($hours / $scheduled * 100, 2) : 0.0];
    }

    /**
     * The last day a schedule applies: the day before the next schedule starts, or null while open-ended.
     */
    public function scheduleEnd(WorkSchedule $schedule): ?Carbon
    {
        $next = $this->schedules->filter(fn (WorkSchedule $other) => $other->effective_from->gt($schedule->effective_from))
            ->sortBy('effective_from')->first();

        return $next?->effective_from->copy()->subDay();
    }

    protected static function booted(): void
    {
        static::saved(fn (Employee $employee) => $employee->syncPerson());
    }

    /**
     * Employees are also people: keep a CRM person with the work contact details in sync (never private data).
     */
    public function syncPerson(): void
    {
        $person = $this->person
            ?? Person::where('email', $this->email)->whereDoesntHave('employee')->first()
            ?? new Person;

        $emailTaken = Person::where('email', $this->email)->whereKeyNot($person->id ?? 0)->exists();

        $person->fill([
            'first_name' => $this->first_name,
            'initials' => $this->initials,
            'last_name_prefix' => $this->last_name_prefix,
            'last_name' => $this->last_name,
            'email' => $emailTaken ? $person->email : $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'linkedin_url' => $this->linkedin_url,
            'twitter_url' => $this->twitter_url,
            'facebook_url' => $this->facebook_url,
            'is_active' => $this->is_employed,
        ])->save();

        if ($this->person_id !== $person->id) {
            $this->person_id = $person->id;
            $this->saveQuietly();
        }

        $this->setRelation('person', $person);
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
            ->when(Arr::wrap($filters['team_id'] ?? []), fn (Builder $query, array $ids) => $query->whereHas('teams', fn (Builder $query) => $query->whereIn('teams.id', $ids)))
            ->when(Arr::wrap($filters['discipline_id'] ?? []), fn (Builder $query, array $ids) => $query->whereHas('disciplines', fn (Builder $query) => $query->whereIn('disciplines.id', $ids)))
            ->when(Arr::wrap($filters['employee_type'] ?? []), fn (Builder $query, array $types) => $query->whereIn('employee_type', $types))
            ->when(Arr::wrap($filters['manager_id'] ?? []), fn (Builder $query, array $ids) => $query->whereIn('manager_id', $ids))
            ->when(Arr::wrap($filters['contract_type'] ?? []), fn (Builder $query, array $types) => $query->whereHas(
                'currentContract', fn (Builder $query) => $query->whereIn('contract_type', $types)
            ))
            ->when(Arr::wrap($filters['access'] ?? []), fn (Builder $query, array $access) => $query->where(function (Builder $query) use ($access): void {
                $query->when(in_array('yes', $access, true), fn (Builder $query) => $query->orWhereHas('user', fn (Builder $user) => $user->where('is_active', true)))
                    ->when(in_array('blocked', $access, true), fn (Builder $query) => $query->orWhereHas('user', fn (Builder $user) => $user->where('is_active', false)))
                    ->when(in_array('no', $access, true), fn (Builder $query) => $query->orWhereNull('user_id'));
            }))
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
