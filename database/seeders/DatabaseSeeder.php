<?php

namespace Database\Seeders;

use App\Enums\BalanceKind;
use App\Enums\LedgerReason;
use App\Models\Discipline;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Team;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\LeaveService;
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
        $admin->update(['user_group_id' => UserGroup::where('name', 'Beheerder')->value('id')]);

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

        if (app()->isLocal() && Employee::count() === 0) {
            $this->seedEmployees($admin);
        }
    }

    private function seedEmployees(User $admin): void
    {
        $teams = collect(['Projecten', 'Ontwikkeling', 'Backoffice'])->map(fn (string $name) => Team::create(['name' => $name]));
        $disciplines = collect(['Projectmanagement', 'Development', 'Design', 'Administratie'])->map(fn (string $name) => Discipline::create(['name' => $name]));

        $manager = Employee::factory()->create([
            'first_name' => 'Admin', 'last_name' => 'Beheerder', 'email' => $admin->email, 'user_id' => $admin->id,
            'job_title' => 'Directeur', 'team_id' => $teams[2]->id, 'discipline_id' => $disciplines[3]->id,
        ]);

        $employees = Employee::factory(11)->create()->each(fn (Employee $employee) => $employee->update([
            'team_id' => $teams->random()->id, 'discipline_id' => $disciplines->random()->id, 'manager_id' => $manager->id,
        ]))->push($manager);

        $day = ['start' => '09:00', 'end' => '17:30', 'break' => 30];
        $alternatingWeek = fn (array $days) => array_fill_keys($days, $day);

        foreach ($employees as $index => $employee) {
            $employee->update(['employee_number' => sprintf('M%04d', $employee->id)]);

            $employee->contracts()->create([
                'contract_type' => $index % 4 === 0 ? 'fixed' : 'indefinite',
                'employment_type' => $index % 3 === 0 ? 'part_time' : 'full_time',
                'start_date' => $employee->hired_at,
                'end_date' => $index % 4 === 0 ? now()->addMonths(6) : null,
                'hours_per_week' => $index % 3 === 0 ? 32 : 40,
            ]);

            $employee->schedules()->create([
                'effective_from' => $employee->hired_at,
                'alternating' => $index % 5 === 0,
                'pattern' => $index % 5 === 0
                    ? ['odd' => $alternatingWeek([1, 2, 3, 4]), 'even' => $alternatingWeek([1, 2, 3, 4, 5])]
                    : ['odd' => $alternatingWeek($index % 3 === 0 ? [1, 2, 3, 4] : [1, 2, 3, 4, 5])],
            ]);

            $employee->rates()->create(['effective_from' => $employee->hired_at, 'cost_rate' => 45 + $index * 2, 'sales_rate' => 90 + $index * 3]);

            $employee->ledgerEntries()->create([
                'kind' => BalanceKind::Vacation, 'reason' => LedgerReason::Allocation, 'hours' => 200,
                'entry_date' => now()->startOfYear(), 'description' => 'Jaarlijkse toekenning '.now()->year,
            ]);
        }

        $leave = app(LeaveService::class);
        $vacation = LeaveType::where('name', 'Vakantie')->firstOrFail();
        $leave->request($employees[1], $vacation, now()->addWeeks(3)->startOfWeek(), now()->addWeeks(3)->startOfWeek()->addDays(4), 40, 'Zomervakantie');
        $employees[2]->absences()->create(['type' => 'sick', 'start_date' => now()->subDays(2), 'percentage' => 100, 'note' => 'Griep']);
    }
}
