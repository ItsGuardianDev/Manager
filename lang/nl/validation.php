<?php

return [
    'required' => ':attribute is verplicht.',
    'email' => ':attribute moet een geldig e-mailadres zijn.',
    'string' => ':attribute moet tekst zijn.',
    'max' => [
        'string' => ':attribute mag niet langer zijn dan :max tekens.',
        'numeric' => ':attribute mag niet groter zijn dan :max.',
        'file' => ':attribute mag niet groter zijn dan :max kilobytes.',
    ],
    'min' => ['numeric' => ':attribute moet minimaal :min zijn.'],
    'integer' => ':attribute moet een geheel getal zijn.',
    'date' => ':attribute moet een geldige datum zijn.',
    'before' => ':attribute moet een datum vóór :date zijn.',
    'exists' => 'De gekozen waarde voor :attribute is ongeldig.',
    'in' => 'De gekozen waarde voor :attribute is ongeldig.',
    'enum' => 'De gekozen waarde voor :attribute is ongeldig.',
    'unique' => ':attribute is al in gebruik.',
    'file' => ':attribute moet een bestand zijn.',
    'mimes' => ':attribute moet een bestand van het type :values zijn.',
    'boolean' => ':attribute moet ja of nee zijn.',

    'attributes' => [
        'email' => 'E-mailadres', 'password' => 'Wachtwoord', 'name' => 'Naam', 'type' => 'Type',
        'phone' => 'Telefoon', 'mobile' => 'Mobiel', 'website' => 'Website', 'linkedin_url' => 'LinkedIn',
        'coc_number' => 'KvK-nummer', 'vat_number' => 'Btw-nummer', 'iban' => 'IBAN', 'bic' => 'BIC',
        'first_name' => 'Voornaam', 'last_name' => 'Achternaam', 'last_name_prefix' => 'Tussenvoegsel',
        'initials' => 'Voorletters', 'gender' => 'Geslacht', 'date_of_birth' => 'Geboortedatum',
        'job_title' => 'Functie', 'department' => 'Afdeling', 'body' => 'Notitie', 'file' => 'Bestand',
        'invoice_delivery' => 'Verstrekkingswijze', 'note' => 'Notitie', 'visit_street' => 'Adres', 'visit_zipcode' => 'Postcode', 'visit_city' => 'Plaatsnaam', 'billing_email' => 'Factuur e-mail', 'payment_term_days' => 'Betalingstermijn',
        'organization_id' => 'Bedrijf', 'person_id' => 'Persoon', 'industry_id' => 'Branche',
        'company_size_id' => 'Bedrijfsgrootte', 'relation_manager_id' => 'Verantwoordelijke',
    ],
];
