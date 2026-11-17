<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\InvoiceMethod;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A service in the catalogue: the template a project service is created from.
 * Blocking keeps the service (and every project service made from it) but hides it for new projects.
 */
#[Guarded(['id'])]
class DefaultService extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_method' => InvoiceMethod::class,
            'billing_cycle' => BillingCycle::class,
            'is_blocked' => 'boolean',
            'allow_hours' => 'boolean',
            'allow_costs' => 'boolean',
            'is_plannable' => 'boolean',
            'price_editable' => 'boolean',
            'price' => 'float',
        ];
    }

    public function revenueGroup(): BelongsTo
    {
        return $this->belongsTo(RevenueGroup::class);
    }

    public function hourLines(): HasMany
    {
        return $this->hasMany(DefaultServiceHourLine::class);
    }

    public function costLines(): HasMany
    {
        return $this->hasMany(DefaultServiceCostLine::class);
    }

    public function projectServices(): HasMany
    {
        return $this->hasMany(ProjectService::class);
    }

    public function budgetHours(): float
    {
        return round($this->hourLines->sum('hours'), 2);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where('name', 'like', "%{$term}%"))
            ->when(($filters['status'] ?? null) === 'blocked', fn (Builder $query) => $query->where('is_blocked', true))
            ->when(($filters['status'] ?? 'available') === 'available', fn (Builder $query) => $query->where('is_blocked', false));
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_blocked', false);
    }
}
