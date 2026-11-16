<?php

namespace App\Models;

use App\Enums\ContractType;
use App\Enums\EmploymentType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Guarded(['id'])]
class EmploymentContract extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contract_type' => ContractType::class,
            'employment_type' => EmploymentType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'hours_per_week' => 'decimal:2',
            'gross_monthly_salary' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isCurrent(?Carbon $on = null): bool
    {
        $on ??= today();

        return $this->start_date->lte($on) && ($this->end_date === null || $this->end_date->gte($on));
    }

    public function isFuture(): bool
    {
        return $this->start_date->isFuture();
    }
}
