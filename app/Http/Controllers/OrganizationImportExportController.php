<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceDelivery;
use App\Enums\OrganizationType;
use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\CompanySize;
use App\Models\CustomerGroup;
use App\Models\Industry;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Services\XlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationImportExportController extends Controller
{
    use HandlesCsv;

    private const FIELDS = [
        'name', 'type', 'industry', 'company_size', 'relation_manager', 'email', 'phone', 'website', 'linkedin_url',
        'coc_number', 'vat_number',
        'visit_street', 'visit_zipcode', 'visit_city', 'visit_country',
        'postal_street', 'postal_zipcode', 'postal_city', 'postal_country',
        'team', 'customer_group', 'invoice_attention', 'invoice_delivery', 'payment_term_days', 'default_vat_code', 'is_vat_exempt',
        'iban', 'bic', 'invoice_template', 'reminder_plan', 'reminder_email', 'is_active',
    ];

    public function form(): View
    {
        return view('organizations.import', ['fields' => self::FIELDS]);
    }

    /**
     * Excel workbook with one tab per subject; ?format=csv gives the flat import-compatible file.
     */
    public function export(Request $request): StreamedResponse|BinaryFileResponse
    {
        $query = Organization::query()->filter($request->query())->orderBy('name');

        if ($request->query('format') === 'csv') {
            $rows = (clone $query)->with(['industry', 'companySize', 'relationManager'])->cursor()
                ->map(fn (Organization $organization) => $this->toRow($organization));

            return $this->streamCsv('bedrijven-'.now()->format('Y-m-d').'.csv', self::FIELDS, $rows);
        }

        $organizations = $query->with(['industry', 'companySize', 'relationManager', 'invoiceContactPerson', 'people', 'notes' => fn ($notes) => $notes->where('type', '!=', 'event'), 'notes.author'])->get();
        $yes = fn (bool $value) => $value ? 'Ja' : 'Nee';

        $companies = $organizations->map(fn (Organization $o) => [
            $o->name, $o->type->label(), $yes($o->is_active), $o->industry?->name, $o->companySize?->name, $o->relationManager?->name,
            $o->email, $o->phone, $o->website, $o->linkedin_url, $o->coc_number, $o->vat_number,
            $o->visit_street, $o->visit_zipcode, $o->visit_city, $o->visit_country,
            $o->postal_street, $o->postal_zipcode, $o->postal_city, $o->postal_country, $o->created_at,
        ]);

        $billing = $organizations->map(fn (Organization $o) => [
            $o->name, $o->invoice_attention->label(), $o->invoiceContactPerson?->full_name, $o->invoice_delivery?->label(),
            Organization::PAYMENT_TERMS[$o->payment_term_days] ?? $o->payment_term_days, Organization::VAT_CODES[$o->default_vat_code] ?? null,
            $o->vat_number, $yes($o->is_vat_exempt), $o->iban, $o->bic, Organization::INVOICE_TEMPLATES[$o->invoice_template] ?? null,
            Organization::REMINDER_PLANS[$o->reminder_plan] ?? null, $o->reminder_email,
        ]);

        $people = $organizations->flatMap(fn (Organization $o) => $o->people->map(fn ($person) => [
            $o->name, $person->full_name, $person->pivot->job_title, $person->pivot->department, $person->email, $person->phone, $person->mobile,
            $yes((bool) $person->pivot->is_primary),
        ]));

        $notes = $organizations->flatMap(fn (Organization $o) => $o->notes->map(fn ($note) => [
            $o->name, $note->created_at, $note->author?->name, $note->body,
        ]));

        return XlsxWriter::make()
            ->sheet('Bedrijven', ['Naam', 'Relatiesoort', 'Actief', 'Branche', 'Bedrijfsgrootte', 'Relatiebeheerder', 'E-mail', 'Telefoon', 'Website', 'LinkedIn', 'KvK-nummer', 'Btw-nummer',
                'Bezoekadres', 'Postcode', 'Plaats', 'Land', 'Postadres', 'Postcode post', 'Plaats post', 'Land post', 'Aangemaakt op'], $companies)
            ->sheet('Facturatie', ['Bedrijf', 'Factuur t.a.v.', 'Contactpersoon', 'Factuurverstrekking', 'Betaaltermijn', 'Standaard btw-code', 'Btw-nummer',
                'Vrijgesteld van btw', 'IBAN', 'BIC', 'Factuurjabloon', 'Herinneringsplan', 'E-mail herinneringen'], $billing)
            ->sheet('Personen', ['Bedrijf', 'Naam', 'Functie', 'Afdeling', 'E-mail', 'Telefoon', 'Mobiel', 'Hoofdcontact'], $people)
            ->sheet('Notities', ['Bedrijf', 'Datum', 'Auteur', 'Notitie'], $notes)
            ->download('bedrijven-'.now()->format('Y-m-d').'.xlsx');
    }

    public function template(): StreamedResponse
    {
        $example = [
            'name' => 'Acme B.V.', 'type' => 'customer', 'industry' => 'IT en software', 'company_size' => '11-50',
            'relation_manager' => 'admin@example.com', 'email' => 'info@acme.test', 'phone' => '+31 20 123 4567',
            'website' => 'https://acme.test', 'coc_number' => '12345678', 'vat_number' => 'NL123456789B01',
            'visit_street' => 'Keizersgracht 1', 'visit_zipcode' => '1015 AA', 'visit_city' => 'Amsterdam',
            'visit_country' => 'Nederland', 'team' => 'Projecten', 'customer_group' => 'Key accounts', 'invoice_attention' => 'department',
            'invoice_delivery' => 'email', 'payment_term_days' => '30', 'default_vat_code' => 'vat_21', 'is_vat_exempt' => 'no',
            'iban' => 'NL91ABNA0417164300', 'invoice_template' => 'standard', 'reminder_plan' => 'default', 'reminder_email' => 'finance@acme.test', 'is_active' => 'yes',
        ];

        return $this->streamCsv('bedrijven-sjabloon.csv', self::FIELDS, [
            array_map(fn ($field) => $example[$field] ?? '', self::FIELDS),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $rows = $this->readCsv($request->file('file'));

        if ($rows === [] || ! array_key_exists('name', reset($rows))) {
            return back()->withErrors(['file' => 'Het bestand is leeg of bevat geen kolom "name". Download het sjabloon voor het juiste formaat.']);
        }

        $result = ['created' => 0, 'skipped' => [], 'errors' => []];
        $seenNames = [];

        foreach ($rows as $line => $row) {
            $data = $this->mapRow($row);
            $normalized = Organization::normalizeName($data['name'] ?? '');

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'type' => ['required', Rule::enum(OrganizationType::class)],
                'email' => ['nullable', 'email:rfc', 'max:255'],
                'reminder_email' => ['nullable', 'email:rfc', 'max:255'],
                'payment_term_days' => ['nullable', 'integer', Rule::in(array_keys(Organization::PAYMENT_TERMS))],
                'invoice_delivery' => ['nullable', Rule::enum(InvoiceDelivery::class)],
                'invoice_attention' => ['required', Rule::in(['none', 'department', 'project_contact'])],
                'default_vat_code' => ['nullable', Rule::in(array_keys(Organization::VAT_CODES))],
                'invoice_template' => ['nullable', Rule::in(array_keys(Organization::INVOICE_TEMPLATES))],
                'reminder_plan' => ['nullable', Rule::in(array_keys(Organization::REMINDER_PLANS))],
            ]);

            if ($validator->fails()) {
                $result['errors'][] = "Regel {$line}: ".$validator->errors()->first();

                continue;
            }

            $duplicate = in_array($normalized, $seenNames, true)
                ? 'dubbel in bestand'
                : $this->findDuplicate($data, $normalized);

            if ($duplicate) {
                $result['skipped'][] = "Regel {$line}: {$data['name']} ({$duplicate})";

                continue;
            }

            $seenNames[] = $normalized;

            Organization::create([
                ...array_diff_key($data, ['team' => 1, 'customer_group' => 1]),
                'team_id' => ($name = $this->nullIfBlank($row['team'] ?? null)) ? Team::firstOrCreate(['name' => $name])->id : null,
                'customer_group_id' => ($name = $this->nullIfBlank($row['customer_group'] ?? null)) ? CustomerGroup::firstOrCreate(['name' => $name])->id : null,
                'industry_id' => ($name = $this->nullIfBlank($row['industry'] ?? null)) ? Industry::firstOrCreate(['name' => $name])->id : null,
                'company_size_id' => ($name = $this->nullIfBlank($row['company_size'] ?? null)) ? CompanySize::firstOrCreate(['name' => $name], ['sort_order' => 99])->id : null,
                'relation_manager_id' => ($email = $this->nullIfBlank($row['relation_manager'] ?? null)) ? User::where('email', $email)->value('id') : null,
            ]);

            $result['created']++;
        }

        return redirect()->route('organizations.import')->with('import_result', $result);
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        $data = [];

        foreach (self::FIELDS as $field) {
            if (in_array($field, ['industry', 'company_size', 'relation_manager'], true)) {
                continue;
            }

            $data[$field] = $this->nullIfBlank($row[$field] ?? null);
        }

        $data['type'] = strtolower($data['type'] ?? 'customer');
        $data['invoice_delivery'] = $data['invoice_delivery'] ? strtolower($data['invoice_delivery']) : null;
        $data['invoice_attention'] = strtolower($data['invoice_attention'] ?? 'none');
        $data['is_vat_exempt'] = $this->parseBoolean($row['is_vat_exempt'] ?? null, false);
        $data['coc_number'] = $data['coc_number'] ? preg_replace('/\s+/', '', $data['coc_number']) : null;
        $data['vat_number'] = $data['vat_number'] ? strtoupper(preg_replace('/[\s.]+/', '', $data['vat_number'])) : null;
        $data['iban'] = $data['iban'] ? strtoupper(preg_replace('/\s+/', '', $data['iban'])) : null;
        $data['is_active'] = $this->parseBoolean($row['is_active'] ?? null);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findDuplicate(array $data, string $normalized): ?string
    {
        if (Organization::where('name_normalized', $normalized)->exists()) {
            return 'naam bestaat al';
        }

        if ($data['coc_number'] && Organization::where('coc_number', $data['coc_number'])->exists()) {
            return 'KvK-nummer bestaat al';
        }

        if ($data['vat_number'] && Organization::where('vat_number', $data['vat_number'])->exists()) {
            return 'btw-nummer bestaat al';
        }

        return null;
    }

    /**
     * @return array<int, mixed>
     */
    private function toRow(Organization $organization): array
    {
        $values = $organization->only(self::FIELDS);
        $values['type'] = $organization->type->value;
        $values['invoice_delivery'] = $organization->invoice_delivery?->value;
        $values['invoice_attention'] = $organization->invoice_attention->value;
        $values['team'] = $organization->team?->name;
        $values['customer_group'] = $organization->customerGroup?->name;
        $values['is_vat_exempt'] = $organization->is_vat_exempt ? 'yes' : 'no';
        $values['industry'] = $organization->industry?->name;
        $values['company_size'] = $organization->companySize?->name;
        $values['relation_manager'] = $organization->relationManager?->email;
        $values['is_active'] = $organization->is_active ? 'yes' : 'no';

        return array_map(fn ($field) => $values[$field] ?? null, self::FIELDS);
    }
}
