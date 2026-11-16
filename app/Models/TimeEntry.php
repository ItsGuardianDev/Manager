<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class TimeEntry extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date', 'hours' => 'float', 'rate' => 'float'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ProjectService::class, 'project_service_id');
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
