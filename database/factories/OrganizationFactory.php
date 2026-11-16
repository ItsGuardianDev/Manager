<?php

namespace Database\Factories;

use App\Enums\OrganizationType;
use App\Models\CompanySize;
use App\Models\Industry;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => fake()->randomElement(OrganizationType::cases()),
            'industry_id' => Industry::query()->inRandomOrder()->value('id'),
            'company_size_id' => CompanySize::query()->inRandomOrder()->value('id'),
            'is_active' => true,
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'website' => fake()->domainName(),
            'coc_number' => fake()->unique()->numerify('########'),
            'vat_number' => fake()->unique()->numerify('NL#########B01'),
            'visit_street' => fake()->streetAddress(),
            'visit_zipcode' => fake()->postcode(),
            'visit_city' => fake()->city(),
            'visit_country' => 'Netherlands',
            'payment_term_days' => 30,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
