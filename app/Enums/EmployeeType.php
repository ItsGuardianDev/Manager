<?php

namespace App\Enums;

enum EmployeeType: string
{
    case Own = 'own';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Own => 'Eigen bedrijf',
            self::External => 'Extern',
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
