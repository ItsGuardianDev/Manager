<?php

namespace App\Enums;

enum NoteType: string
{
    case Note = 'note';
    case Email = 'email';
    case Phone = 'phone';
    case Meeting = 'meeting';

    public function label(): string
    {
        return match ($this) {
            self::Note => 'Notitie',
            self::Email => 'E-mail',
            self::Phone => 'Telefoongesprek',
            self::Meeting => 'Gesprek',
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
