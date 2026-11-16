<?php

namespace App\Http\Requests;

use App\Enums\EmployeeType;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'bsn' => $this->filled('bsn') ? preg_replace('/\D/', '', (string) $this->input('bsn')) : null,
            'iban' => $this->filled('iban') ? strtoupper(preg_replace('/\s+/', '', (string) $this->input('iban'))) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'initials' => ['nullable', 'string', 'max:20'],
            'last_name_prefix' => ['nullable', 'string', 'max:30'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('employees', 'email')->ignore($employee)],
            'employee_number' => ['nullable', 'string', 'max:30', Rule::unique('employees', 'employee_number')->ignore($employee)],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['exists:teams,id'],
            'discipline_ids' => ['nullable', 'array'],
            'discipline_ids.*' => ['exists:disciplines,id'],
            'employee_type' => ['nullable', Rule::enum(EmployeeType::class)],
            'photo' => ['nullable', 'image', 'max:3072'],
            'person_id' => ['nullable', 'exists:people,id'],
            'manager_id' => ['nullable', 'exists:employees,id', Rule::notIn(array_filter([$employee?->id]))],
            'hired_at' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'private_email' => ['nullable', 'email:rfc', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'bsn' => ['nullable', 'digits:9', fn ($attribute, $value, $fail) => $this->passesElevenTest((string) $value) ?: $fail('Het BSN is ongeldig.')],
            'iban' => ['nullable', 'regex:/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'marital_status' => ['nullable', Rule::in(array_keys(Employee::MARITAL_STATUSES))],
            'private_phone' => ['nullable', 'string', 'max:50'],
            'linkedin_url' => ['nullable', 'string', 'max:255'],
            'twitter_url' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'string', 'max:255'],
            'user_group_id' => ['nullable', 'exists:user_groups,id'],
            'invite' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'voornaam', 'last_name' => 'achternaam', 'email' => 'e-mailadres', 'employee_number' => 'personeelsnummer',
            'manager_id' => 'leidinggevende', 'hired_at' => 'datum in dienst', 'date_of_birth' => 'geboortedatum',
            'private_email' => 'privé e-mail', 'photo' => 'profielfoto', 'employee_type' => 'soort medewerker', 'iban' => 'IBAN', 'bsn' => 'BSN',
        ];
    }

    private function passesElevenTest(string $bsn): bool
    {
        $sum = 0;
        foreach (str_split($bsn) as $index => $digit) {
            $sum += (int) $digit * ($index === 8 ? -1 : 9 - $index);
        }

        return $sum % 11 === 0;
    }
}
