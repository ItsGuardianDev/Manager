<?php

namespace App\Enums;

enum InvoiceMethod: string
{
    case FixedPrice = 'fixed_price';
    case Subscription = 'subscription';
    case AfterCalculation = 'after_calculation';

    public function label(): string
    {
        return match ($this) {
            self::FixedPrice => 'Vaste prijs',
            self::Subscription => 'Abonnement',
            self::AfterCalculation => 'Nacalculatie',
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
