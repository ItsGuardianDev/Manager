<?php

namespace App\Http\Requests;

use App\Enums\InvoiceAttention;
use App\Enums\InvoiceDelivery;
use App\Enums\OrganizationType;
use App\Models\Organization;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->boolean('postal_differs')) {
            $this->merge(['postal_street' => null, 'postal_zipcode' => null, 'postal_city' => null, 'postal_country' => null]);
        }

        $this->merge([
            'coc_number' => $this->filled('coc_number') ? preg_replace('/\s+/', '', $this->string('coc_number')->toString()) : null,
            'vat_number' => $this->filled('vat_number') ? strtoupper(preg_replace('/[\s.]+/', '', $this->string('vat_number')->toString())) : null,
            'iban' => $this->filled('iban') ? strtoupper(preg_replace('/\s+/', '', $this->string('iban')->toString())) : null,
            'is_active' => $this->boolean('is_active', true),
            'is_vat_exempt' => $this->boolean('is_vat_exempt'),
            'invoice_attention' => $this->input('invoice_attention') ?: 'none',
            'reminder_email' => $this->boolean('use_reminder_email') ? $this->input('reminder_email') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');
        $ignoreId = $organization?->id;

        return [
            'name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) use ($ignoreId): void {
                $duplicate = Organization::query()
                    ->where('name_normalized', Organization::normalizeName((string) $value))
                    ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                    ->first();

                if ($duplicate) {
                    $fail("Er bestaat al een bedrijf met deze naam: {$duplicate->name}.");
                }
            }],
            'type' => ['required', Rule::enum(OrganizationType::class)],
            'industry_id' => ['nullable', 'exists:industries,id'],
            'company_size_id' => ['nullable', 'exists:company_sizes,id'],
            'relation_manager_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['boolean'],
            'postal_differs' => ['boolean'],
            'note' => ['nullable', 'string', 'max:5000'],

            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'string', 'max:255'],
            'coc_number' => ['nullable', 'string', 'max:20', Rule::unique('organizations', 'coc_number')->ignore($ignoreId)],
            'vat_number' => ['nullable', 'string', 'max:30', Rule::unique('organizations', 'vat_number')->ignore($ignoreId)],

            'visit_street' => ['nullable', 'string', 'max:255'],
            'visit_zipcode' => ['nullable', 'string', 'max:20'],
            'visit_city' => ['nullable', 'string', 'max:255'],
            'visit_country' => ['nullable', 'string', 'max:100'],
            'postal_street' => ['nullable', 'string', 'max:255'],
            'postal_zipcode' => ['nullable', 'string', 'max:20'],
            'postal_city' => ['nullable', 'string', 'max:255'],
            'postal_country' => ['nullable', 'string', 'max:100'],

            'team_id' => ['nullable', 'exists:teams,id'],
            'customer_group_id' => ['nullable', 'exists:customer_groups,id'],

            'invoice_attention' => ['nullable', Rule::enum(InvoiceAttention::class)],
            'invoice_contact_person_id' => ['nullable', 'exists:people,id'],
            'invoice_delivery' => ['nullable', Rule::enum(InvoiceDelivery::class)],
            'payment_term_days' => ['nullable', 'integer', Rule::in(array_keys(Organization::PAYMENT_TERMS))],
            'default_vat_code' => ['nullable', Rule::in(array_keys(Organization::VAT_CODES))],
            'is_vat_exempt' => ['boolean'],
            'iban' => ['nullable', 'string', 'max:34'],
            'bic' => ['nullable', 'string', 'max:11'],
            'invoice_template' => ['nullable', Rule::in(array_keys(Organization::INVOICE_TEMPLATES))],
            'reminder_plan' => ['nullable', Rule::in(array_keys(Organization::REMINDER_PLANS))],
            'use_reminder_email' => ['nullable', 'boolean'],
            'reminder_email' => ['nullable', 'email:rfc', 'max:255'],
        ];
    }
}
