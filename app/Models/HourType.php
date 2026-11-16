<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['id'])]
class HourType extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['default_rate' => 'decimal:2'];
    }
}
