<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => (string) fake()->unique()->numberBetween(1000, 99999),
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'status' => 'active',
            'is_invoiceable' => true,
            'start_date' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
