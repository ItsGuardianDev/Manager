<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class ServiceHourLine extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['hours' => 'float', 'rate' => 'float'];
    }

    public function hourType(): BelongsTo
    {
        return $this->belongsTo(HourType::class);
    }

    public function amount(): float
    {
        return round($this->hours * $this->rate, 2);
    }
}
