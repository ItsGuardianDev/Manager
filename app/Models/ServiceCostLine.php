<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class ServiceCostLine extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'float', 'purchase_price' => 'float', 'margin_percentage' => 'float'];
    }

    public function costType(): BelongsTo
    {
        return $this->belongsTo(CostType::class);
    }

    public function purchaseTotal(): float
    {
        return round($this->quantity * $this->purchase_price, 2);
    }

    public function sellingTotal(): float
    {
        return round($this->purchaseTotal() * (1 + $this->margin_percentage / 100), 2);
    }
}
