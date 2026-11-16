<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actief',
            self::Completed => 'Afgerond',
            self::Closed => 'Gesloten',
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
