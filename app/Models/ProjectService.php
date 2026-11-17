<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\InvoiceMethod;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A service of a project: what is sold, how it is invoiced and which hours and costs are budgeted for it.
 * Budget figures use the eager-loaded hourLines, costLines, timeEntries and purchaseLines.
 */
#[Guarded(['id'])]
class ProjectService extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_method' => InvoiceMethod::class,
            'is_active' => 'boolean',
            'allow_hours' => 'boolean',
            'allow_costs' => 'boolean',
            'is_plannable' => 'boolean',
            'billing_cycle' => BillingCycle::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'invoice_from' => 'date',
            'price' => 'float',
            'discount_percentage' => 'float',
            'hours_alerted_at' => 'datetime',
            'costs_alerted_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function revenueGroup(): BelongsTo
    {
        return $this->belongsTo(RevenueGroup::class);
    }

    public function defaultService(): BelongsTo
    {
        return $this->belongsTo(DefaultService::class);
    }

    public function hourLines(): HasMany
    {
        return $this->hasMany(ServiceHourLine::class);
    }

    public function costLines(): HasMany
    {
        return $this->hasMany(ServiceCostLine::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function purchaseLines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    private function discountFactor(): float
    {
        return 1 - min(100, max(0, $this->discount_percentage)) / 100;
    }

    /** Price after discount (fixed price and subscription services). */
    public function netPrice(): ?float
    {
        return $this->price === null ? null : round($this->price * $this->discountFactor(), 2);
    }

    public function budgetHours(): float
    {
        return round($this->hourLines->sum('hours'), 2);
    }

    /** Hour budget in euros after discount. */
    public function budgetHoursAmount(): float
    {
        return round($this->hourLines->sum(fn (ServiceHourLine $line) => $line->amount()) * $this->discountFactor(), 2);
    }

    public function spentHours(): float
    {
        return round($this->timeEntries->sum('hours'), 2);
    }

    public function spentHoursAmount(): float
    {
        return round($this->timeEntries->sum(fn (TimeEntry $entry) => $entry->amount()), 2);
    }

    public function remainingHours(): float
    {
        return round($this->budgetHours() - $this->spentHours(), 2);
    }

    public function remainingHoursAmount(): float
    {
        return round($this->budgetHoursAmount() - $this->spentHoursAmount(), 2);
    }

    /** Cost budget at purchase prices. */
    public function budgetCosts(): float
    {
        return round($this->costLines->sum(fn (ServiceCostLine $line) => $line->purchaseTotal()), 2);
    }

    /** Cost budget at selling prices (including margin and discount). */
    public function sellingCosts(): float
    {
        return round($this->costLines->sum(fn (ServiceCostLine $line) => $line->sellingTotal()) * $this->discountFactor(), 2);
    }

    public function spentCosts(): float
    {
        return round($this->purchaseLines->sum(fn (PurchaseLine $line) => $line->total()), 2);
    }

    public function remainingCosts(): float
    {
        return round($this->budgetCosts() - $this->spentCosts(), 2);
    }

    /** Budget used for hours, as a percentage (can exceed 100). */
    public function hoursConsumption(): int
    {
        return $this->budgetHours() > 0 ? (int) round($this->spentHours() / $this->budgetHours() * 100) : 0;
    }

    public function costsConsumption(): int
    {
        return $this->budgetCosts() > 0 ? (int) round($this->spentCosts() / $this->budgetCosts() * 100) : 0;
    }

    public function isOverHoursBudget(): bool
    {
        return $this->budgetHours() > 0 && $this->spentHours() > $this->budgetHours();
    }

    public function isOverCostBudget(): bool
    {
        return $this->budgetCosts() > 0 && $this->spentCosts() > $this->budgetCosts();
    }
}
