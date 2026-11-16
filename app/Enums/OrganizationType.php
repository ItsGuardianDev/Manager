<?php

namespace App\Enums;

enum OrganizationType: string
{
    case Customer = 'customer';
    case Lead = 'lead';
    case Supplier = 'supplier';
    case Own = 'own';
    case Partner = 'partner';
    case Prospect = 'prospect';
    case Competitor = 'competitor';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Klant',
            self::Lead => 'Lead',
            self::Supplier => 'Leverancier',
            self::Own => 'Mijn bedrijf',
            self::Partner => 'Partner',
            self::Prospect => 'Prospect',
            self::Competitor => 'Concurrent',
        };
    }

    /**
     * Tailwind classes used to render the relation-type badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Customer => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::Lead => 'bg-yellow-50 text-yellow-800 ring-yellow-600/20',
            self::Supplier => 'bg-orange-50 text-orange-700 ring-orange-600/20',
            self::Own => 'bg-slate-100 text-slate-700 ring-slate-500/20',
            self::Partner => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            self::Prospect => 'bg-violet-50 text-violet-700 ring-violet-600/20',
            self::Competitor => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        };
    }
}
