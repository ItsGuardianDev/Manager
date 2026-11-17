<?php

namespace Database\Seeders;

use App\Enums\BalanceKind;
use App\Enums\LedgerReason;
use App\Models\CostType;
use App\Models\DefaultService;
use App\Models\Discipline;
use App\Models\Employee;
use App\Models\HourType;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Project;
use App\Models\RevenueGroup;
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

        if (app()->isLocal() && Project::count() === 0) {
            $this->seedCatalog();
            $this->seedProjects();
        }
    }

    private function seedProjects(): void
    {
        $manager = Employee::where('email', 'admin@example.com')->first();
        $organizations = Organization::inRandomOrder()->limit(4)->get();
        $architect = HourType::where('name', 'Architect')->first();
        $drawer = HourType::where('name', 'Tekenaar')->first();
        $outsourcing = CostType::where('name', 'Inhuur derden')->first();

        foreach (['Nieuwbouw Ijburg', 'Hoofdkantoor Nederland', 'Ontwerp stadion', 'Renovatie Bajeskwartier'] as $index => $name) {
            $project = Project::create([
                'number' => (string) (1001 + $index), 'name' => $name, 'organization_id' => $organizations[$index]->id,
                'manager_id' => $manager?->id, 'start_date' => now()->subMonths(2), 'is_invoiceable' => true,
            ]);

            $service = $project->services()->create(['name' => 'Schetsontwerp', 'invoice_method' => 'fixed_price', 'price' => 45050, 'sort_order' => 1, 'completion_percentage' => 40]);
            $service->hourLines()->create(['hour_type_id' => $architect->id, 'hours' => 120, 'rate' => 125]);
            $service->hourLines()->create(['hour_type_id' => $drawer->id, 'hours' => 80, 'rate' => 95]);
            $service->costLines()->create(['cost_type_id' => $outsourcing->id, 'quantity' => 1, 'purchase_price' => 20000, 'margin_percentage' => 0]);

            $second = $project->services()->create(['name' => 'Definitief ontwerp', 'invoice_method' => 'after_calculation', 'sort_order' => 2]);
            $second->hourLines()->create(['hour_type_id' => $architect->id, 'hours' => 200, 'rate' => 125]);

            if ($manager && $index < 3) {
                $project->timeEntries()->create(['employee_id' => $manager->id, 'project_service_id' => $service->id, 'hour_type_id' => $architect->id, 'date' => now()->subDays(5 + $index), 'hours' => 30 + $index * 60, 'rate' => 125, 'note' => 'Schetsen', 'status' => 'approved']);
            }

            if ($manager && $index === 0) {
                $monday = now()->startOfWeek();

                foreach ([[0, '09:00', '12:30', 3.5, 'draft', 'Schetsen uitwerken'], [1, '09:00', '17:00', 8, 'submitted', 'Overleg en tekenwerk'], [2, null, null, 6, 'draft', 'Voorbereiding']] as [$offset, $start, $end, $hours, $status, $note]) {
                    $project->timeEntries()->create(['employee_id' => $manager->id, 'project_service_id' => $service->id, 'hour_type_id' => $architect->id, 'date' => $monday->copy()->addDays($offset), 'start_time' => $start, 'end_time' => $end, 'hours' => $hours, 'rate' => 125, 'note' => $note, 'status' => $status]);
                }
            }
        }
    }

    private function seedCatalog(): void
    {
        $architect = HourType::where('name', 'Architect')->first();
        $drawer = HourType::where('name', 'Tekenaar')->first();
        $group = RevenueGroup::first();

        foreach ([['Schetsontwerp', 'fixed_price', 45050, 120, 80], ['Voorontwerp', 'fixed_price', 60000, 200, 120], ['Uitvoering', 'after_calculation', null, 0, 0], ['Interne werkzaamheden', 'after_calculation', null, 0, 0]] as [$name, $method, $price, $architectHours, $drawerHours]) {
            $service = DefaultService::where('name', $name)->first();
            $service?->update(['invoice_method' => $method, 'price' => $price, 'vat_code' => 'vat_21', 'revenue_group_id' => $group?->id, 'description' => $name === 'Schetsontwerp' ? 'Eerste schetsen met de opdrachtgever.' : null]);

            if ($service && $architectHours > 0) {
                $service->hourLines()->create(['hour_type_id' => $architect->id, 'hours' => $architectHours, 'rate' => 125]);
                $service->hourLines()->create(['hour_type_id' => $drawer->id, 'hours' => $drawerHours, 'rate' => 95]);
            }
        }

        DefaultService::where('name', 'Interne werkzaamheden')->update(['allow_costs' => false]);
    }

    private function seedEmployees(User $admin): void
    {
        $teams = collect(['Projecten', 'Ontwikkeling', 'Backoffice'])->map(fn (string $name) => Team::create(['name' => $name]));
        $disciplines = collect(['Projectmanagement', 'Development', 'Design', 'Administratie'])->map(fn (string $name) => Discipline::create(['name' => $name]));

        $manager = Employee::factory()->create([
            'first_name' => 'Admin', 'last_name' => 'Beheerder', 'email' => $admin->email, 'user_id' => $admin->id,
            'job_title' => 'Directeur',
        ]);
        $manager->teams()->attach($teams[2]);
        $manager->disciplines()->attach($disciplines[3]);

        $employees = Employee::factory(11)->create()->each(function (Employee $employee) use ($teams, $disciplines, $manager): void {
            $employee->update(['manager_id' => $manager->id]);
            $employee->teams()->attach($teams->random(random_int(1, 2))->pluck('id'));
            $employee->disciplines()->attach($disciplines->random()->id);
        })->push($manager);

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
                'cost_rate' => 45 + $index * 2,
                'sales_rate' => 90 + $index * 3,
                'alternating' => $index % 5 === 0,
                'pattern' => $index % 5 === 0
                    ? ['odd' => $alternatingWeek([1, 2, 3, 4]), 'even' => $alternatingWeek([1, 2, 3, 4, 5])]
                    : ['odd' => $alternatingWeek($index % 3 === 0 ? [1, 2, 3, 4] : [1, 2, 3, 4, 5])],
            ]);

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
