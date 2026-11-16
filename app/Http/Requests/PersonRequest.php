<?php

namespace App\Http\Requests;

use App\Models\Person;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ignoreId = $this->route('person')?->id;

        return [
            'initials' => ['nullable', 'string', 'max:20'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name_prefix' => ['nullable', 'string', 'max:30'],
            'last_name' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, Closure $fail) use ($ignoreId): void {
                if ($this->boolean('confirm_duplicate')) {
                    return;
                }

                $duplicate = Person::query()
                    ->where('full_name_normalized', Person::normalizeFullName(
                        $this->input('first_name'), $this->input('last_name_prefix'), (string) $value,
                    ))
                    ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                    ->first();

                if ($duplicate) {
                    $this->session()->flash('duplicate_person_id', $duplicate->id);
                    $fail("Er bestaat al een persoon met de naam {$duplicate->full_name}.");
                }
            }],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'email' => ['nullable', 'email:rfc', 'max:255', Rule::unique('people', 'email')->ignore($ignoreId)],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'linkedin_url' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],

            'organization_id' => ['nullable', 'exists:organizations,id'],
            'job_title' => ['nullable', 'string', 'max:150'],
        ];
    }
}
