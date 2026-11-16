<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->unique()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'job_title' => fake()->jobTitle(),
            'hired_at' => fake()->dateTimeBetween('-5 years', '-1 month'),
            'is_active' => true,
        ];
    }

    public function left(): static
    {
        return $this->state(['is_active' => false, 'left_at' => now()->subMonth()]);
    }
}
