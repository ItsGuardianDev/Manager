<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case Contract = 'contract';
    case Identification = 'identification';
    case Review = 'review';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Contract',
            self::Identification => 'Identiteitsbewijs',
            self::Review => 'Beoordeling',
            self::Other => 'Overig',
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
