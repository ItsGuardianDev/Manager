<?php

namespace App\Enums;

enum InvoiceAttention: string
{
    case None = 'none';
    case Department = 'department';
    case ContactPerson = 'contact_person';
    case ProjectContact = 'project_contact';

    public function label(): string
    {
        return match ($this) {
            self::None => 'T.a.v. niet gebruiken',
            self::Department => 'Afdeling',
            self::ContactPerson => 'Contactpersoon',
            self::ProjectContact => 'Contactpersoon die bij het project vermeld staat',
        };
    }
}
