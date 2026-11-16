<?php

namespace App\Enums;

enum AbsenceType: string
{
    case Sick = 'sick';
    case Pregnancy = 'pregnancy';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Sick => 'Ziekte',
            self::Pregnancy => 'Zwangerschaps- of bevallingsverlof',
            self::Other => 'Overig verzuim',
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
