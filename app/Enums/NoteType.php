<?php

namespace App\Enums;

enum NoteType: string
{
    case Note = 'note';
    case Email = 'email';
    case Phone = 'phone';
    case Meeting = 'meeting';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::Note => 'Notitie',
            self::Email => 'E-mail',
            self::Phone => 'Telefoongesprek',
            self::Meeting => 'Gesprek',
            self::Event => 'Gebeurtenis',
        };
    }

    /**
     * Types a user can pick when writing a message (events are system generated).
     *
     * @return array<string, string>
     */
    public static function selectable(): array
    {
        return array_diff_key(self::options(), ['event' => true]);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases()), 'label', 'value');
    }
}
