<?php

namespace App\Http\Requests;

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

            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_attention' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email:rfc', 'max:255'],
            'billing_street' => ['nullable', 'string', 'max:255'],
            'billing_zipcode' => ['nullable', 'string', 'max:20'],
            'billing_city' => ['nullable', 'string', 'max:255'],
            'billing_country' => ['nullable', 'string', 'max:100'],
            'iban' => ['nullable', 'string', 'max:34'],
            'bic' => ['nullable', 'string', 'max:11'],
            'debtor_number' => ['nullable', 'string', 'max:50'],
            'invoice_reference' => ['nullable', 'string', 'max:255'],
            'invoice_delivery' => ['nullable', Rule::enum(InvoiceDelivery::class)],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }
}
