<?php

namespace Database\Factories;

use App\Enums\Permission;
use App\Models\UserGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserGroup>
 */
class UserGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => fake()->unique()->word().' groep', 'permissions' => []];
    }

    /**
     * @param  array<int, Permission>  $permissions
     */
    public function with(array $permissions): static
    {
        return $this->state(['permissions' => array_map(fn (Permission $permission) => $permission->value, $permissions)]);
    }

    public function administrators(): static
    {
        return $this->state(['permissions' => Permission::values()]);
    }
}
