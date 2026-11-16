<?php

namespace App\Enums;

enum LedgerReason: string
{
    case Allocation = 'allocation';
    case Taken = 'taken';
    case Correction = 'correction';
    case ExtraHours = 'extra_hours';
    case Conversion = 'conversion';
    case Carryover = 'carryover';

    public function label(): string
    {
        return match ($this) {
            self::Allocation => 'Toekenning',
            self::Taken => 'Opname',
            self::Correction => 'Correctie',
            self::ExtraHours => 'Extra uren',
            self::Conversion => 'Omzetting',
            self::Carryover => 'Overdracht',
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
