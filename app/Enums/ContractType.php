<?php

namespace App\Enums;

enum ContractType: string
{
    case Indefinite = 'indefinite';
    case Fixed = 'fixed';
    case Freelance = 'freelance';
    case Intern = 'intern';
    case OnCall = 'on_call';

    public function label(): string
    {
        return match ($this) {
            self::Indefinite => 'Onbepaalde tijd',
            self::Fixed => 'Bepaalde tijd',
            self::Freelance => 'Freelance / ZZP',
            self::Intern => 'Stage',
            self::OnCall => 'Oproepcontract',
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
