<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class Purchase extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date', 'status' => PurchaseStatus::class];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'supplier_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    public function total(): float
    {
        return round($this->lines->sum(fn (PurchaseLine $line) => $line->total()), 2);
    }
}
