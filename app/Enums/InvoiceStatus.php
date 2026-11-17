<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Concept',
            self::Sent => 'Verzonden',
            self::Paid => 'Betaald',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'bg-amber-100 text-amber-800',
            self::Sent => 'bg-sky-100 text-sky-800',
            self::Paid => 'bg-emerald-100 text-emerald-800',
        };
    }
}
