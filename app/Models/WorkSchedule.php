<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pattern shape: ['odd' => [isoWeekday => ['start' => 'H:i', 'end' => 'H:i', 'break' => minutes]], 'even' => [...]].
 * Non-alternating schedules only use the 'odd' key for every week.
 */
#[Guarded(['id'])]
class WorkSchedule extends Model
{
    public const WEEKDAYS = [1 => 'Maandag', 2 => 'Dinsdag', 3 => 'Woensdag', 4 => 'Donderdag', 5 => 'Vrijdag', 6 => 'Zaterdag', 7 => 'Zondag'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['effective_from' => 'date', 'alternating' => 'boolean', 'pattern' => 'array'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function weekKey(Carbon $date): string
    {
        return $this->alternating && $date->isoWeek() % 2 === 0 ? 'even' : 'odd';
    }

    public function hoursOn(Carbon $date): float
    {
        return self::dayHours($this->pattern[$this->weekKey($date)][$date->dayOfWeekIso] ?? null);
    }

    public function weeklyHours(string $week = 'odd'): float
    {
        return round(array_sum(array_map(fn ($day) => self::dayHours($day), $this->pattern[$week] ?? [])), 2);
    }

    /**
     * @param  array{start?: ?string, end?: ?string, break?: int|string|null}|null  $day
     */
    public static function dayHours(?array $day): float
    {
        if (! $day || empty($day['start']) || empty($day['end'])) {
            return 0.0;
        }

        [$startHour, $startMinute] = array_map('intval', explode(':', $day['start']));
        [$endHour, $endMinute] = array_map('intval', explode(':', $day['end']));
        $minutes = ($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute) - (int) ($day['break'] ?? 0);

        return max(0, round($minutes / 60, 2));
    }
}
