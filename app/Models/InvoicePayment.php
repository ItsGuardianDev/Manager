<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class InvoicePayment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'float', 'paid_on' => 'date'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
