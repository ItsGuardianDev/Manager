<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Enums\ContractType;
use App\Enums\EmploymentType;
use App\Enums\InvoiceAttention;
use App\Enums\InvoiceDelivery;
use App\Enums\InvoiceMethod;
use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Makes files exported from Simplicate (Dutch headings, Dutch values, Excel number and date formats)
 * look like our own import format: canonical English headings and the values our importers expect.
 */
class ImportNormalizer
{
    /**
     * Dutch and English headings per field. A heading can fit several fields (such as "postcode");
     * the first candidate that the importer knows wins.
     *
     * @var array<string, array<int, string>>
     */
    private const HEADINGS = [
        'name' => ['naam', 'naam bedrijf', 'bedrijfsnaam', 'organisatie', 'bedrijf naam', 'naam dienst', 'dienst'],
        'type' => ['relatiesoort', 'soort relatie', 'relatietype', 'soort'],
        'industry' => ['branche'],
        'company_size' => ['bedrijfsgrootte', 'grootte'],
        'relation_manager' => ['relatiebeheerder', 'accountmanager'],
        'email' => ['e mail', 'email', 'e mailadres', 'emailadres', 'e mailadres werk', 'zakelijk e mailadres'],
        'phone' => ['telefoon', 'telefoonnummer', 'tel', 'telefoonnummer werk', 'telefoon werk'],
        'mobile' => ['mobiel', 'mobiel nummer', 'mobiele telefoon', 'mobiel werk', 'mobiel telefoonnummer', 'mobiele nummer'],
        'website' => ['website', 'web', 'url'],
        'linkedin_url' => ['linkedin', 'linkedin url'],
        'coc_number' => ['kvk', 'kvk nummer', 'nummer kvk', 'kvk nr', 'kamer van koophandel'],
        'vat_number' => ['btw nummer', 'btw nr', 'btwnummer', 'btw id'],
        'visit_street' => ['adres', 'bezoekadres', 'adres bezoekadres', 'straat', 'straat bezoekadres'],
        'visit_zipcode' => ['postcode', 'postcode bezoekadres'],
        'visit_city' => ['plaats', 'plaatsnaam', 'plaats bezoekadres', 'stad'],
        'visit_country' => ['land', 'land bezoekadres'],
        'postal_street' => ['postadres', 'adres postadres', 'straat postadres'],
        'postal_zipcode' => ['postcode post', 'postcode postadres'],
        'postal_city' => ['plaats post', 'plaats postadres', 'plaatsnaam postadres'],
        'postal_country' => ['land post', 'land postadres'],
        'team' => ['team', 'teams'],
        'customer_group' => ['klantgroep', 'klantengroep'],
        'invoice_attention' => ['factuur t a v', 'factuur tav', 't a v', 'tav'],
        'invoice_delivery' => ['factuurverstrekking', 'verstrekkingswijze', 'verstrekking', 'verzendmethode'],
        'payment_term_days' => ['betaaltermijn', 'betalingstermijn', 'betaaltermijn dagen'],
        'default_vat_code' => ['standaard btw code', 'standaard btw', 'btw code', 'btw percentage'],
        'is_vat_exempt' => ['vrijgesteld van btw', 'btw vrijgesteld', 'vrijgesteld'],
        'iban' => ['iban', 'bankrekening', 'iban nummer'],
        'bic' => ['bic', 'swift'],
        'invoice_template' => ['factuurjabloon', 'jabloon', 'factuursjabloon'],
        'reminder_plan' => ['herinneringsplan', 'herinneringen'],
        'reminder_email' => ['e mail herinneringen', 'email herinneringen', 'herinnering e mail'],
        'is_active' => ['actief', 'status', 'is actief'],
        'first_name' => ['voornaam', 'roepnaam'],
        'initials' => ['initialen', 'voorletters'],
        'last_name_prefix' => ['tussenvoegsel', 'tussenvoegsels', 'voorvoegsel'],
        'last_name' => ['achternaam', 'familienaam'],
        'gender' => ['geslacht'],
        'date_of_birth' => ['geboortedatum', 'geboren'],
        'street' => ['adres', 'straat', 'adres prive'],
        'zipcode' => ['postcode', 'postcode prive'],
        'city' => ['plaats', 'plaatsnaam', 'woonplaats', 'stad'],
        'country' => ['land'],
        'company' => ['bedrijf', 'bedrijven', 'organisatie', 'klant', 'debiteur', 'relatie'],
        'job_title' => ['functie', 'functienaam', 'titel'],
        'department' => ['afdeling'],
        'employee_number' => ['personeelsnummer', 'medewerkernummer', 'medewerker nummer', 'nummer medewerker', 'nummer'],
        'discipline' => ['discipline', 'disciplines'],
        'manager_email' => ['leidinggevende', 'manager', 'projectleider', 'leidinggevende e mail', 'e mail leidinggevende'],
        'hired_at' => ['datum in dienst', 'in dienst', 'in dienst sinds', 'indienstdatum', 'startdatum in dienst', 'datum indiensttreding'],
        'left_at' => ['datum uit dienst', 'uit dienst', 'uitdienstdatum', 'einddatum dienstverband'],
        'private_email' => ['e mail prive', 'email prive', 'privemail', 'e mailadres prive'],
        'contract_type' => ['contractsoort', 'soort contract', 'contracttype'],
        'employment_type' => ['dienstverband', 'soort dienstverband', 'fulltime parttime'],
        'contract_start' => ['startdatum contract', 'begindatum contract', 'contract start', 'contract begin'],
        'contract_end' => ['einddatum contract', 'contract einde', 'contract eind'],
        'hours_per_week' => ['uren per week', 'contracturen', 'uren p w', 'contracturen per week'],
        'project_number' => ['projectnummer', 'project nummer', 'nummer project'],
        'project_name' => ['projectnaam', 'project', 'naam project'],
        'start_date' => ['startdatum', 'begindatum', 'start', 'begin'],
        'end_date' => ['einddatum', 'einde', 'eind'],
        'is_invoiceable' => ['project factureren', 'factureren', 'factureerbaar'],
        'service_name' => ['dienst', 'dienstnaam', 'naam dienst'],
        'invoice_method' => ['factuurmethode', 'facturatiemethode', 'methode'],
        'price' => ['prijs', 'vaste prijs', 'bedrag', 'dienstprijs'],
        'discount_percentage' => ['korting', 'korting percentage', 'kortingspercentage'],
        'hour_type' => ['urensoort', 'soort uren', 'uursoort'],
        'hours' => ['uren', 'aantal uren', 'budget uren', 'urenbudget'],
        'hourly_rate' => ['uurtarief', 'tarief', 'tarief per uur'],
        'cost_type' => ['kostensoort', 'soort kosten'],
        'cost_quantity' => ['aantal', 'hoeveelheid', 'aantal kosten'],
        'purchase_price' => ['inkoopprijs', 'inkoop', 'inkoop prijs'],
        'margin_percentage' => ['marge', 'marge percentage', 'winstmarge'],
        'description' => ['omschrijving', 'beschrijving', 'toelichting'],
        'internal_note' => ['interne notitie', 'interne opmerking', 'notitie intern'],
        'price_editable' => ['prijs aanpasbaar', 'prijs wijzigbaar'],
        'billing_cycle' => ['factuurperiode', 'betaalperiode', 'periode', 'facturatieperiode'],
        'vat_code' => ['btw', 'btw tarief', 'btw code'],
        'revenue_group' => ['omzetgroep'],
        'allow_hours' => ['uren toestaan', 'uren schrijven toegestaan'],
        'allow_costs' => ['kosten toestaan'],
        'is_plannable' => ['planbaar', 'inplanbaar'],
        'is_blocked' => ['geblokkeerd', 'afgesloten'],
    ];

    private const DATE_FIELDS = ['date_of_birth', 'hired_at', 'left_at', 'contract_start', 'contract_end', 'start_date', 'end_date'];

    private const NUMBER_FIELDS = ['price', 'discount_percentage', 'hours', 'hourly_rate', 'cost_quantity', 'purchase_price', 'margin_percentage', 'hours_per_week'];

    /** @var array<string, array<int, string>>|null */
    private ?array $lookup = null;

    /**
     * Canonical field for a file heading, or the heading itself (lower-cased) when it is unknown.
     *
     * @param  array<int, string>  $fields  fields the importer understands
     */
    public function field(string $heading, array $fields): string
    {
        $key = $this->key($heading);
        $direct = strtolower(trim($heading));

        if (in_array($direct, $fields, true)) {
            return $direct;
        }

        foreach ($this->lookup()[$key] ?? [] as $candidate) {
            if (in_array($candidate, $fields, true)) {
                return $candidate;
            }
        }

        return $direct;
    }

    /**
     * Value in the form the importers expect for a field.
     */
    public function value(string $field, string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            return $this->date($value);
        }

        if (in_array($field, self::NUMBER_FIELDS, true)) {
            return $this->number($value);
        }

        return match ($field) {
            'type' => $this->option($value, $this->labels(OrganizationType::cases())),
            'invoice_delivery' => $this->option($value, $this->labels(InvoiceDelivery::cases())),
            'invoice_attention' => $this->invoiceAttention($value),
            'contract_type' => $this->option($value, $this->labels(ContractType::cases())),
            'employment_type' => $this->option($value, $this->labels(EmploymentType::cases())),
            'invoice_method' => $this->option($value, $this->labels(InvoiceMethod::cases())),
            'billing_cycle' => $this->option($value, $this->labels(BillingCycle::cases())),
            'default_vat_code', 'vat_code' => $this->option($value, Organization::VAT_CODES),
            'invoice_template' => $this->option($value, Organization::INVOICE_TEMPLATES),
            'reminder_plan' => $this->option($value, Organization::REMINDER_PLANS),
            'gender' => $this->option($value, ['male' => 'Man', 'female' => 'Vrouw', 'other' => 'Anders', 'unknown' => 'Onbekend']),
            'payment_term_days' => $this->paymentTerm($value),
            default => $value,
        };
    }

    private function key(string $heading): string
    {
        $ascii = Str::ascii($heading);
        $ascii = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $ascii) ?? '');

        return trim($ascii);
    }

    /**
     * @return array<string, array<int, string>> normalised heading => candidate fields
     */
    private function lookup(): array
    {
        if ($this->lookup === null) {
            $this->lookup = [];

            foreach (self::HEADINGS as $field => $headings) {
                foreach ($headings as $heading) {
                    $this->lookup[$this->key($heading)][] = $field;
                }
            }
        }

        return $this->lookup;
    }

    /**
     * @param  array<int, \BackedEnum&object>  $cases
     * @return array<string, string>
     */
    private function labels(array $cases): array
    {
        return array_column(array_map(fn ($case) => ['value' => $case->value, 'label' => method_exists($case, 'label') ? $case->label() : $case->value], $cases), 'label', 'value');
    }

    /**
     * @param  array<string|int, string>  $options  value => label
     */
    private function option(string $input, array $options): string
    {
        $wanted = $this->key($input);

        foreach ($options as $value => $label) {
            if ($wanted === $this->key((string) $value) || $wanted === $this->key($label)) {
                return (string) $value;
            }
        }

        return $input;
    }

    private function invoiceAttention(string $value): string
    {
        $matched = $this->option($value, $this->labels(InvoiceAttention::cases()));

        if ($matched !== $value) {
            return $matched;
        }

        return match (true) {
            str_contains($this->key($value), 'project') => 'project_contact',
            str_contains($this->key($value), 'niet') => 'none',
            default => $value,
        };
    }

    private function paymentTerm(string $value): string
    {
        if (in_array($this->key($value), ['direct', 'contant'], true)) {
            return '0';
        }

        return preg_match('/\d+/', $value, $match) ? $match[0] : $value;
    }

    private function date(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10);
        }

        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $value, $m)) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : $value;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * Dutch notation (1.234,56 and € 12,50) to a plain decimal; plain numbers stay as they are.
     */
    private function number(string $value): string
    {
        $clean = preg_replace('/[^\d,.\-]/', '', $value) ?? $value;

        if ($clean === '') {
            return $value;
        }

        if (str_contains($clean, ',')) {
            return str_replace(',', '.', str_replace('.', '', $clean));
        }

        return preg_match('/^-?\d{1,3}(\.\d{3})+$/', $clean) ? str_replace('.', '', $clean) : $clean;
    }
}
