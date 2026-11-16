<?php

namespace App\Enums;

enum BalanceKind: string
{
    case Vacation = 'vacation';
    case TimeForTime = 'tvt';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Vacation => 'Vakantie-uren',
            self::TimeForTime => 'Tijd-voor-tijd',
            self::None => 'Geen saldo',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases()), 'label', 'value');
    }
}
