<?php

namespace App\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Maandelijks',
            self::Quarterly => 'Per kwartaal',
            self::HalfYearly => 'Halfjaarlijks',
            self::Yearly => 'Jaarlijks',
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
