<?php

namespace App\Models;

use App\Enums\BalanceKind;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['id'])]
class LeaveType extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['balance_kind' => BalanceKind::class, 'is_active' => 'boolean'];
    }
}
