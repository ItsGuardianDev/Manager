<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class InvoiceLine extends Model
{
    public const TYPES = [
        'hours' => 'Uren', 'fixed_price' => 'Vaste prijs', 'subscription' => 'Abonnement', 'kilometers' => 'Kilometers',
        'installment' => 'Termijn', 'costs' => 'Kosten', 'advance' => 'Voorschot', 'credit' => 'Credit', 'custom' => 'Overig',
    ];

    public const VAT_RATES = [21 => '21%', 9 => '9%', 0 => '0%'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_price' => 'float', 'vat_rate' => 'float', 'discount_percentage' => 'float'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ProjectService::class, 'project_service_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Amount excluding VAT after discount. */
    public function amount(): float
    {
        return round($this->quantity * $this->unit_price * (1 - min(100, max(0, $this->discount_percentage)) / 100), 2);
    }

    public function vatAmount(): float
    {
        return round($this->amount() * $this->vat_rate / 100, 2);
    }
}
