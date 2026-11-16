<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceDelivery;
use App\Enums\OrganizationType;
use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\CompanySize;
use App\Models\Industry;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationImportExportController extends Controller
{
    use HandlesCsv;

    private const FIELDS = [
        'name', 'type', 'industry', 'company_size', 'relation_manager', 'email', 'phone', 'website', 'linkedin_url',
        'coc_number', 'vat_number',
        'visit_street', 'visit_zipcode', 'visit_city', 'visit_country',
        'postal_street', 'postal_zipcode', 'postal_city', 'postal_country',
        'billing_name', 'billing_attention', 'billing_email', 'billing_street', 'billing_zipcode', 'billing_city',
        'billing_country', 'iban', 'bic', 'debtor_number', 'invoice_reference', 'invoice_delivery', 'payment_term_days', 'is_active',
    ];

    public function form(): View
    {
        return view('organizations.import', ['fields' => self::FIELDS]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = Organization::query()
            ->with(['industry', 'companySize', 'relationManager'])
            ->filter($request->query())
            ->orderBy('name')
            ->cursor()
            ->map(fn (Organization $organization) => $this->toRow($organization));

        return $this->streamCsv('bedrijven-'.now()->format('Y-m-d').'.csv', self::FIELDS, $rows);
    }

    public function template(): StreamedResponse
    {
        $example = [
            'name' => 'Acme B.V.', 'type' => 'customer', 'industry' => 'IT en software', 'company_size' => '11-50',
            'relation_manager' => 'admin@example.com', 'email' => 'info@acme.test', 'phone' => '+31 20 123 4567',
            'website' => 'https://acme.test', 'coc_number' => '12345678', 'vat_number' => 'NL123456789B01',
            'visit_street' => 'Keizersgracht 1', 'visit_zipcode' => '1015 AA', 'visit_city' => 'Amsterdam',
            'visit_country' => 'Netherlands', 'billing_email' => 'finance@acme.test', 'iban' => 'NL91ABNA0417164300',
            'debtor_number' => 'D-1001', 'invoice_reference' => 'PO-2026', 'payment_term_days' => '30', 'is_active' => 'yes',
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
                'billing_email' => ['nullable', 'email:rfc', 'max:255'],
                'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
                'invoice_delivery' => ['nullable', Rule::enum(InvoiceDelivery::class)],
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
                ...$data,
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
        $values['industry'] = $organization->industry?->name;
        $values['company_size'] = $organization->companySize?->name;
        $values['relation_manager'] = $organization->relationManager?->email;
        $values['is_active'] = $organization->is_active ? 'yes' : 'no';

        return array_map(fn ($field) => $values[$field] ?? null, self::FIELDS);
    }
}
