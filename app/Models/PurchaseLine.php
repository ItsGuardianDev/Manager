<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class PurchaseLine extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_price' => 'float'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ProjectService::class, 'project_service_id');
    }

    public function costType(): BelongsTo
    {
        return $this->belongsTo(CostType::class);
    }

    public function total(): float
    {
        return round($this->quantity * $this->unit_price, 2);
    }
}
