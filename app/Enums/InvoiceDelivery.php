<?php

namespace App\Enums;

enum InvoiceDelivery: string
{
    case Email = 'email';
    case Pdf = 'pdf';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Pdf => 'PDF afdrukken',
        };
    }
}
