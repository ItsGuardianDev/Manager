<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class DefaultServiceCostLine extends Model
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
}
