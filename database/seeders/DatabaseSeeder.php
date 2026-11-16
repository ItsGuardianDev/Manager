<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LookupSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => 'password'],
        );

        if (app()->isLocal() && Organization::count() === 0) {
            $employees = User::factory(3)->create()->push($admin);

            $organizations = Organization::factory(15)->create()
                ->each(fn (Organization $organization) => $organization->update(['relation_manager_id' => $employees->random()->id]));

            Person::factory(30)->create()->each(function (Person $person) use ($organizations): void {
                $organizations->random(random_int(1, 2))->each(fn (Organization $organization) => $person->organizations()->attach($organization->id, [
                    'job_title' => fake()->jobTitle(),
                    'is_primary' => $person->organizations()->doesntExist(),
                ]));
            });
        }
    }
}
