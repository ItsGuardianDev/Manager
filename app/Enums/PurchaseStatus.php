<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case ToProcess = 'to_process';
    case Processed = 'processed';

    public function label(): string
    {
        return match ($this) {
            self::ToProcess => 'Te verwerken',
            self::Processed => 'Verwerkt',
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
