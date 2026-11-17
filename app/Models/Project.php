<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Enums\PurchaseStatus;
use App\Models\Concerns\HasTimeline;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

#[Guarded(['id'])]
class Project extends Model
{
    use HasFactory, HasTimeline;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'is_on_hold' => 'boolean',
            'is_invoiceable' => 'boolean',
            'track_kilometers' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
            'kilometer_price' => 'float',
        ];
    }

    /**
     * Next free project number: highest numeric number plus one.
     */
    public static function nextNumber(): string
    {
        $highest = static::query()->pluck('number')->filter(fn ($number) => ctype_digit((string) $number))->map(fn ($number) => (int) $number)->max() ?? 0;

        return (string) ($highest + 1);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class)->orderBy('last_name');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_team')->orderBy('name');
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copied_from_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(ProjectService::class)->orderBy('sort_order')->orderBy('id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->orderByDesc('date')->orderByDesc('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Invoiced so far, excluding VAT; credit invoices reduce it. Requires invoices.lines to be loaded. */
    public function invoicedAmount(): float
    {
        return round($this->invoices->where('status', '!=', InvoiceStatus::Draft)->sum(fn (Invoice $invoice) => $invoice->subtotal()), 2);
    }

    /** Value of draft invoices, excluding VAT. Requires invoices.lines to be loaded. */
    public function draftInvoiceAmount(): float
    {
        return round($this->invoices->where('status', InvoiceStatus::Draft)->sum(fn (Invoice $invoice) => $invoice->subtotal()), 2);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class)->orderByDesc('date')->orderByDesc('id');
    }

    /**
     * Without members or teams every employee may register on the project.
     */
    public function allowsEmployee(Employee $employee): bool
    {
        if (! $this->members()->exists() && ! $this->teams()->exists()) {
            return true;
        }

        return $this->members()->whereKey($employee->id)->exists()
            || $this->teams()->whereHas('employees', fn (Builder $query) => $query->whereKey($employee->id))->exists();
    }

    /**
     * Services must be loaded with their budget relations (see Project::BUDGET_RELATIONS).
     */
    public const BUDGET_RELATIONS = ['services.hourLines', 'services.costLines', 'services.timeEntries', 'services.purchaseLines', 'services.invoiceLines.invoice', 'invoices.lines'];

    private function activeServices()
    {
        return $this->services->where('is_active', true);
    }

    public function budgetHours(): float
    {
        return round($this->activeServices()->sum(fn (ProjectService $s) => $s->budgetHours()), 2);
    }

    public function spentHours(): float
    {
        return round($this->services->sum(fn (ProjectService $s) => $s->spentHours()), 2);
    }

    public function budgetHoursAmount(): float
    {
        return round($this->activeServices()->sum(fn (ProjectService $s) => $s->budgetHoursAmount()), 2);
    }

    public function spentHoursAmount(): float
    {
        return round($this->services->sum(fn (ProjectService $s) => $s->spentHoursAmount()), 2);
    }

    public function budgetCosts(): float
    {
        return round($this->activeServices()->sum(fn (ProjectService $s) => $s->budgetCosts()), 2);
    }

    public function spentCosts(): float
    {
        return round($this->services->sum(fn (ProjectService $s) => $s->spentCosts()), 2);
    }

    /** Total budget: hours (after discount) plus cost budget. */
    public function totalBudget(): float
    {
        return round($this->budgetHoursAmount() + $this->budgetCosts(), 2);
    }

    public function totalSpent(): float
    {
        return round($this->spentHoursAmount() + $this->spentCosts(), 2);
    }

    public function totalRemaining(): float
    {
        return round($this->totalBudget() - $this->totalSpent(), 2);
    }

    public function consumption(): int
    {
        return $this->totalBudget() > 0 ? (int) round($this->totalSpent() / $this->totalBudget() * 100) : 0;
    }

    public function isOverBudget(): bool
    {
        return $this->totalBudget() > 0 && $this->totalSpent() > $this->totalBudget();
    }

    /** Average completion of the active services, weighted by their hour budget. */
    public function progress(): int
    {
        $services = $this->activeServices();

        if ($services->isEmpty()) {
            return 0;
        }

        $weight = $services->sum(fn (ProjectService $s) => $s->budgetHours());

        if ($weight <= 0) {
            return (int) round($services->avg('completion_percentage'));
        }

        return (int) round($services->sum(fn (ProjectService $s) => $s->completion_percentage * $s->budgetHours()) / $weight);
    }

    public function unprocessedPurchaseCount(): int
    {
        return $this->purchases()->where('status', PurchaseStatus::ToProcess->value)->count();
    }

    public function daysSinceUpdate(): int
    {
        return (int) $this->updated_at->diffInDays(now());
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));

        return $query
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('number', 'like', $like)
                    ->orWhereHas('organization', fn (Builder $query) => $query->where('name', 'like', $like)));
            })
            ->when(Arr::wrap($filters['organization_id'] ?? []), fn (Builder $query, array $ids) => $query->whereIn('organization_id', $ids))
            ->when(Arr::wrap($filters['manager_id'] ?? []), fn (Builder $query, array $ids) => $query->whereIn('manager_id', $ids))
            ->when(Arr::wrap($filters['employee_id'] ?? []), fn (Builder $query, array $ids) => $query->where(fn (Builder $query) => $query
                ->whereIn('manager_id', $ids)
                ->orWhereHas('members', fn (Builder $query) => $query->whereIn('employees.id', $ids))
                ->orWhereHas('timeEntries', fn (Builder $query) => $query->whereIn('employee_id', $ids))))
            ->when(Arr::wrap($filters['invoice_method'] ?? []), fn (Builder $query, array $methods) => $query->whereHas('services', fn (Builder $query) => $query->whereIn('invoice_method', $methods)))
            ->when(Arr::wrap($filters['default_service_id'] ?? []), fn (Builder $query, array $ids) => $query->whereHas('services', fn (Builder $query) => $query->whereIn('default_service_id', $ids)))
            ->when(isset($filters['on_hold']) && $filters['on_hold'] !== '', fn (Builder $query) => $query->where('is_on_hold', (bool) $filters['on_hold']))
            ->when(isset($filters['invoiceable']) && $filters['invoiceable'] !== '', fn (Builder $query) => $query->where('is_invoiceable', (bool) $filters['invoiceable']))
            ->when(($filters['copied'] ?? '') === '1', fn (Builder $query) => $query->whereNotNull('copied_from_id'))
            ->when(($filters['copied'] ?? '') === '0', fn (Builder $query) => $query->whereNull('copied_from_id'))
            ->when(! empty($filters['created_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['created_from']))
            ->when(! empty($filters['created_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['created_to']));
    }

    public function scopeInStatus(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'closed' => $query->whereIn('status', [ProjectStatus::Completed->value, ProjectStatus::Closed->value]),
            'all' => $query,
            default => $query->where('status', ProjectStatus::Active->value),
        };
    }
}
