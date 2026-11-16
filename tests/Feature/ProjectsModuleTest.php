<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\CostType;
use App\Models\Employee;
use App\Models\HourType;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Team;
use App\Models\User;
use App\Models\UserGroup;
use App\Notifications\BudgetExceeded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectsModuleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['user_group_id' => UserGroup::where('name', 'Beheerder')->value('id')]);
    }

    /**
     * @param  array<int, Permission>  $permissions
     */
    private function userWith(array $permissions): User
    {
        return User::factory()->create(['user_group_id' => UserGroup::factory()->with($permissions)->create()->id]);
    }

    /**
     * A project with one fixed-price service: 10 hours at € 100 and a € 500 cost budget.
     */
    private function projectWithBudget(?Employee $manager = null): Project
    {
        $project = Project::factory()->create(['organization_id' => Organization::factory()->create()->id, 'manager_id' => $manager?->id]);
        $service = $project->services()->create(['name' => 'Ontwerp', 'invoice_method' => 'fixed_price', 'price' => 5000, 'sort_order' => 1]);
        $service->hourLines()->create(['hour_type_id' => HourType::where('name', 'Architect')->value('id'), 'hours' => 10, 'rate' => 100]);
        $service->costLines()->create(['cost_type_id' => CostType::where('name', 'Inhuur derden')->value('id'), 'quantity' => 1, 'purchase_price' => 500, 'margin_percentage' => 20]);

        return $project->fresh();
    }

    public function test_projects_require_login_and_permission(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('projects.index'))->assertForbidden();
        $this->actingAs($this->userWith([Permission::ProjectsView]))->get(route('projects.index'))->assertOk();
        $this->actingAs($this->userWith([Permission::ProjectsView]))->post(route('projects.store'), ['name' => 'X'])->assertForbidden();
    }

    public function test_project_gets_unique_sequential_number_and_needs_a_customer_unless_internal(): void
    {
        $admin = $this->admin();
        $organization = Organization::factory()->create();

        $this->actingAs($admin)->post(route('projects.store'), ['name' => 'Eerste', 'organization_id' => $organization->id])->assertRedirect();
        $this->actingAs($admin)->post(route('projects.store'), ['name' => 'Tweede', 'organization_id' => $organization->id])->assertRedirect();
        $this->actingAs($admin)->post(route('projects.store'), ['name' => 'Zonder klant'])->assertSessionHasErrors('organization_id');
        $this->actingAs($admin)->post(route('projects.store'), ['name' => 'Intern', 'is_invoiceable' => 0])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('projects.store'), ['name' => 'Dubbel', 'organization_id' => $organization->id, 'number' => '1'])->assertSessionHasErrors('number');

        $this->assertSame(['1', '2', '3'], Project::orderBy('id')->pluck('number')->all());
        $internal = Project::where('name', 'Intern')->firstOrFail();
        $this->assertNull($internal->organization_id);
        $this->assertFalse($internal->is_invoiceable);
        $this->assertStringContainsString('Project aangemaakt', $internal->notes()->firstOrFail()->body);
    }

    public function test_settings_link_customer_contact_manager_dates_and_members(): void
    {
        $admin = $this->admin();
        $project = $this->projectWithBudget();
        $manager = Employee::factory()->create();
        $member = Employee::factory()->create();
        $team = Team::factory()->create();

        $this->actingAs($admin)->put(route('projects.update', $project), [
            'name' => 'Nieuwe naam', 'number' => $project->number, 'organization_id' => $project->organization_id, 'manager_id' => $manager->id,
            'start_date' => '2026-01-01', 'end_date' => '2025-01-01',
        ])->assertSessionHasErrors('end_date');

        $this->actingAs($admin)->put(route('projects.update', $project), [
            'name' => 'Nieuwe naam', 'number' => $project->number, 'organization_id' => $project->organization_id, 'manager_id' => $manager->id,
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_on_hold' => 1, 'member_ids' => [$member->id], 'team_ids' => [$team->id],
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('Nieuwe naam', $project->name);
        $this->assertSame($manager->id, $project->manager_id);
        $this->assertTrue($project->is_on_hold);
        $this->assertTrue($project->allowsEmployee($member));
        $this->assertFalse($project->allowsEmployee(Employee::factory()->create()));
    }

    public function test_service_budget_hours_rates_costs_and_discount_are_calculated(): void
    {
        $project = $this->projectWithBudget();
        $service = $project->services()->with(Project::BUDGET_RELATIONS ? ['hourLines', 'costLines', 'timeEntries', 'purchaseLines'] : [])->first();

        $this->assertSame(10.0, $service->budgetHours());
        $this->assertSame(1000.0, $service->budgetHoursAmount());
        $this->assertSame(500.0, $service->budgetCosts());
        $this->assertSame(600.0, $service->sellingCosts());

        $service->discount_percentage = 10;
        $this->assertSame(900.0, $service->budgetHoursAmount());
        $this->assertSame(4500.0, $service->netPrice());
        $this->assertSame(540.0, $service->sellingCosts());
    }

    public function test_creating_a_service_with_lines_through_the_form(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $architect = HourType::where('name', 'Architect')->firstOrFail();
        $inhuur = CostType::where('name', 'Inhuur derden')->firstOrFail();

        $this->actingAs($admin)->post(route('projects.services.store', $project), [
            'name' => 'Schetsontwerp', 'invoice_method' => 'subscription', 'price' => 1000, 'discount_percentage' => 10, 'invoice_from' => '2026-02-01',
            'hour_lines' => [['hour_type_id' => $architect->id, 'hours' => 8, 'rate' => ''], ['hour_type_id' => '', 'hours' => 5]],
            'cost_lines' => [['cost_type_id' => $inhuur->id, 'quantity' => 2, 'purchase_price' => 100, 'margin_percentage' => '']],
        ])->assertSessionHasNoErrors();

        $service = $project->services()->with(['hourLines', 'costLines'])->firstOrFail();
        $this->assertSame(1, $service->hourLines->count());
        $this->assertSame((float) $architect->default_rate, $service->hourLines->first()->rate);
        $this->assertSame(200.0, $service->budgetCosts());
        $this->assertSame(900.0, $service->netPrice());

        $this->actingAs($admin)->post(route('projects.services.store', $project), ['name' => 'Nacalc', 'invoice_method' => 'after_calculation', 'price' => 999])->assertSessionHasNoErrors();
        $this->assertNull($project->services()->where('name', 'Nacalc')->firstOrFail()->price, 'After-calculation services carry no fixed price.');
        $this->actingAs($admin)->post(route('projects.services.store', $project), ['name' => 'Fout', 'invoice_method' => 'bogus'])->assertSessionHasErrors('invoice_method');
    }

    public function test_services_can_be_reordered_deactivated_and_only_deleted_when_unused(): void
    {
        $admin = $this->admin();
        $project = $this->projectWithBudget();
        $second = $project->services()->create(['name' => 'Tweede', 'invoice_method' => 'after_calculation', 'sort_order' => 2]);
        $first = $project->services()->where('name', 'Ontwerp')->firstOrFail();

        $this->actingAs($admin)->patch(route('projects.services.move', [$project, $second]), ['direction' => 'up']);
        $this->assertSame(['Tweede', 'Ontwerp'], $project->services()->pluck('name')->all());

        $this->actingAs($admin)->patch(route('projects.services.toggle-active', [$project, $first]));
        $this->assertFalse($first->fresh()->is_active);
        $this->actingAs($admin)->patch(route('projects.services.progress', [$project, $second]), ['completion_percentage' => 60]);
        $this->assertSame(60, $second->fresh()->completion_percentage);

        $project->timeEntries()->create(['employee_id' => Employee::factory()->create()->id, 'project_service_id' => $first->id, 'date' => today(), 'hours' => 1, 'rate' => 100]);
        $this->actingAs($admin)->delete(route('projects.services.destroy', [$project, $first]))->assertSessionHasErrors('service');
        $this->actingAs($admin)->delete(route('projects.services.destroy', [$project, $second]))->assertRedirect();
        $this->assertSame(1, $project->services()->count());
    }

    public function test_hours_and_purchases_count_against_the_budget_and_progress_is_shown(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create();
        $project = $this->projectWithBudget();
        $service = $project->services()->firstOrFail();
        $architect = HourType::where('name', 'Architect')->firstOrFail();
        $inhuur = CostType::where('name', 'Inhuur derden')->firstOrFail();

        $this->actingAs($admin)->post(route('projects.hours.store', $project), [
            'employee_id' => $employee->id, 'project_service_id' => $service->id, 'hour_type_id' => $architect->id, 'date' => today()->format('Y-m-d'), 'hours' => 4,
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('purchases.store'), [
            'project_id' => $project->id, 'date' => today()->format('Y-m-d'), 'description' => 'Constructeur', 'type' => 'invoice',
            'lines' => [['project_service_id' => $service->id, 'cost_type_id' => $inhuur->id, 'quantity' => 1, 'unit_price' => 200]],
        ])->assertSessionHasNoErrors();

        $project->load(Project::BUDGET_RELATIONS);
        $this->assertSame(4.0, $project->spentHours());
        $this->assertSame(400.0, $project->spentHoursAmount(), 'The service rate is used, not the default hour type rate.');
        $this->assertSame(200.0, $project->spentCosts());
        $this->assertSame(1500.0, $project->totalBudget());
        $this->assertSame(600.0, $project->totalSpent());
        $this->assertSame(900.0, $project->totalRemaining());
        $this->assertSame(40, $project->consumption());
        $this->assertFalse($project->isOverBudget());

        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk()->assertSee('Constructeur')->assertSee('Architect');
        $this->actingAs($admin)->get(route('projects.index'))->assertOk()->assertSee($project->name)->assertSee('40%');
    }

    public function test_exceeding_the_budget_notifies_the_project_manager_once(): void
    {
        $admin = $this->admin();
        $managerUser = User::factory()->create();
        $manager = Employee::factory()->create(['user_id' => $managerUser->id]);
        $project = $this->projectWithBudget($manager);
        $service = $project->services()->firstOrFail();
        $employee = Employee::factory()->create();
        $payload = ['employee_id' => $employee->id, 'project_service_id' => $service->id, 'hour_type_id' => HourType::where('name', 'Architect')->value('id'), 'date' => today()->format('Y-m-d')];

        $this->actingAs($admin)->post(route('projects.hours.store', $project), $payload + ['hours' => 8]);
        $this->assertSame(0, $managerUser->notifications()->count());

        $this->actingAs($admin)->post(route('projects.hours.store', $project), $payload + ['hours' => 4]);
        $this->assertSame(1, $managerUser->notifications()->count());
        $this->assertSame($project->id, $managerUser->notifications()->first()->data['project_id']);

        $this->actingAs($admin)->post(route('projects.hours.store', $project), $payload + ['hours' => 2]);
        $this->assertSame(1, $managerUser->notifications()->count(), 'Only the first overrun is reported.');

        $this->actingAs($managerUser)->get(route('notifications.index'))->assertOk()->assertSee('urenbudget');
        $this->actingAs($managerUser)->post(route('notifications.read-all'));
        $this->assertSame(0, $managerUser->unreadNotifications()->count());

        $entry = $project->timeEntries()->latest('id')->first();
        $project->timeEntries()->delete();
        $this->actingAs($admin)->post(route('projects.hours.store', $project), $payload + ['hours' => 1]);
        $this->assertNull($service->fresh()->hours_alerted_at, 'The alert re-arms once the project is back under budget.');
        $this->assertNotNull($entry);
    }

    public function test_cost_overrun_also_notifies_and_falls_back_to_users_who_can_edit_projects(): void
    {
        $admin = $this->admin();
        $project = $this->projectWithBudget();
        $service = $project->services()->firstOrFail();

        $this->actingAs($admin)->post(route('purchases.store'), [
            'project_id' => $project->id, 'date' => today()->format('Y-m-d'), 'description' => 'Te duur', 'type' => 'expense',
            'lines' => [['project_service_id' => $service->id, 'quantity' => 1, 'unit_price' => 600]],
        ]);

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame('costs', $admin->notifications()->first()->data['kind']);
        $this->assertSame(BudgetExceeded::class, $admin->notifications()->first()->type);
    }

    public function test_only_assigned_employees_can_register_hours_and_employees_register_for_themselves(): void
    {
        $project = $this->projectWithBudget();
        $service = $project->services()->firstOrFail();
        $viewer = $this->userWith([Permission::ProjectsView]);
        $own = Employee::factory()->create(['user_id' => $viewer->id]);
        $other = Employee::factory()->create();
        $payload = ['project_service_id' => $service->id, 'date' => today()->format('Y-m-d'), 'hours' => 2];

        $this->actingAs($viewer)->post(route('projects.hours.store', $project), $payload + ['employee_id' => $other->id])->assertForbidden();
        $this->actingAs($viewer)->post(route('projects.hours.store', $project), $payload + ['employee_id' => $own->id])->assertSessionHasNoErrors();

        $project->members()->attach(Employee::factory()->create());
        $this->actingAs($viewer)->post(route('projects.hours.store', $project), $payload + ['employee_id' => $own->id])->assertSessionHasErrors('employee_id');
    }

    public function test_project_closes_only_when_administration_is_done_and_can_be_reopened(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create();
        $project = $this->projectWithBudget();
        $service = $project->services()->firstOrFail();
        $this->actingAs($admin)->post(route('purchases.store'), [
            'project_id' => $project->id, 'date' => today()->format('Y-m-d'), 'description' => 'Factuur', 'type' => 'invoice',
            'lines' => [['project_service_id' => $service->id, 'unit_price' => 50]],
        ]);

        $this->actingAs($admin)->post(route('projects.close', $project), ['status' => 'closed', 'force' => 1])->assertSessionHasErrors('close');
        $this->assertSame('active', $project->fresh()->status->value);

        $this->actingAs($admin)->patch(route('purchases.process', Purchase::firstOrFail()));
        $this->actingAs($admin)->post(route('projects.close', $project), ['status' => 'closed'])->assertSessionHasErrors('close');
        $this->actingAs($admin)->post(route('projects.close', $project), ['status' => 'closed', 'force' => 1])->assertSessionHasNoErrors();
        $this->assertSame('closed', $project->fresh()->status->value);

        $this->actingAs($admin)->post(route('projects.hours.store', $project), ['employee_id' => $employee->id, 'project_service_id' => $service->id, 'date' => today()->format('Y-m-d'), 'hours' => 1])->assertSessionHasErrors('hours');
        $this->actingAs($admin)->get(route('projects.index'))->assertDontSee($project->name);
        $this->actingAs($admin)->get(route('projects.index', ['tab' => 'closed']))->assertSee($project->name);

        $this->actingAs($admin)->patch(route('projects.reopen', $project));
        $this->assertSame('active', $project->fresh()->status->value);
    }

    public function test_project_can_be_copied_with_services_and_budgets_but_without_registrations(): void
    {
        $admin = $this->admin();
        $project = $this->projectWithBudget();
        $service = $project->services()->firstOrFail();
        $project->members()->attach($employee = Employee::factory()->create());
        $project->timeEntries()->create(['employee_id' => $employee->id, 'project_service_id' => $service->id, 'date' => today(), 'hours' => 3, 'rate' => 100]);
        $service->update(['completion_percentage' => 70]);

        $this->actingAs($admin)->post(route('projects.copy', $project), ['name' => 'Kopie', 'number' => ''])->assertRedirect();

        $copy = Project::where('name', 'Kopie')->firstOrFail();
        $copy->load(Project::BUDGET_RELATIONS);
        $this->assertSame($project->id, $copy->copied_from_id);
        $this->assertNotSame($project->number, $copy->number);
        $this->assertSame(10.0, $copy->budgetHours());
        $this->assertSame(500.0, $copy->budgetCosts());
        $this->assertSame(0.0, $copy->spentHours());
        $this->assertSame(0, $copy->services->first()->completion_percentage);
        $this->assertTrue($copy->members->contains($employee));

        $this->actingAs($admin)->get(route('projects.index', ['copied' => 1]))->assertSee('Kopie');
    }

    public function test_projects_can_be_filtered_by_name_number_customer_employee_and_method(): void
    {
        $admin = $this->admin();
        $a = $this->projectWithBudget();
        $a->update(['name' => 'Stadion Noord', 'number' => '500']);
        $b = Project::factory()->create(['name' => 'Brugproject', 'number' => '600', 'organization_id' => Organization::factory()->create(['name' => 'Gemeente Utrecht'])->id]);
        $b->services()->create(['name' => 'Los', 'invoice_method' => 'after_calculation', 'sort_order' => 1]);
        $employee = Employee::factory()->create();
        $b->members()->attach($employee);

        $see = fn (array $query) => $this->actingAs($admin)->get(route('projects.index', $query));

        $see(['q' => 'Stadion'])->assertSee('Stadion Noord')->assertDontSee('Brugproject');
        $see(['q' => '600'])->assertSee('Brugproject')->assertDontSee('Stadion Noord');
        $see(['q' => 'Utrecht'])->assertSee('Brugproject')->assertDontSee('Stadion Noord');
        $see(['employee_id' => [$employee->id]])->assertSee('Brugproject')->assertDontSee('Stadion Noord');
        $see(['invoice_method' => ['fixed_price']])->assertSee('Stadion Noord')->assertDontSee('Brugproject');
        $see(['organization_id' => [$b->organization_id]])->assertSee('Brugproject')->assertDontSee('Stadion Noord');
    }

    public function test_internal_project_documents_and_timeline_messages(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $project = Project::factory()->create(['is_invoiceable' => false]);

        $this->actingAs($admin)->post(route('projects.documents.store', $project), ['file' => UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('projects.notes.store', $project), ['body' => 'Kick-off gehouden', 'type' => 'meeting'])->assertRedirect();

        $this->assertSame('plan', $project->documents()->firstOrFail()->title);
        $this->assertSame('meeting', $project->notes()->where('type', 'meeting')->firstOrFail()->type);
        $this->actingAs($this->userWith([Permission::ProjectsView]))->post(route('projects.notes.store', $project), ['body' => 'x'])->assertForbidden();
        $this->actingAs($this->userWith([Permission::ProjectsView]))->get(route('documents.show', $project->documents()->firstOrFail()))->assertOk();
    }

    public function test_lookups_can_be_managed_and_used_ones_are_protected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('project-settings.store', 'hour-types'), ['name' => 'Jurist', 'default_rate' => 140])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('project-settings.store', 'hour-types'), ['name' => 'Jurist'])->assertSessionHasErrors('name');
        $type = HourType::where('name', 'Jurist')->firstOrFail();
        $this->actingAs($admin)->put(route('project-settings.update', ['hour-types', $type->id]), ['name' => 'Juridisch adviseur', 'default_rate' => 150]);
        $this->assertSame('150.00', $type->fresh()->default_rate);
        $this->actingAs($admin)->post(route('project-settings.store', 'default-services'), ['name' => 'Oplevering', 'invoice_method' => 'fixed_price'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('project-settings.index'))->assertOk()->assertSee('Juridisch adviseur');
        $this->actingAs($admin)->post(route('project-settings.store', 'bogus'), ['name' => 'x'])->assertNotFound();

        $project = $this->projectWithBudget();
        $used = $project->services()->first()->hourLines()->first()->hour_type_id;
        $this->actingAs($admin)->delete(route('project-settings.destroy', ['hour-types', $used]))->assertSessionHasErrors('lookup');
    }

    public function test_projects_can_be_imported_with_services_and_budgets_and_exported(): void
    {
        $admin = $this->admin();
        Organization::factory()->create(['name' => 'Gemeente Amsterdam']);
        $csv = "project_number;project_name;company;is_invoiceable;service_name;invoice_method;price;hour_type;hours;hourly_rate;cost_type;cost_quantity;purchase_price\n"
            ."1001;Nieuwbouw Ijburg;Gemeente Amsterdam;yes;Schetsontwerp;fixed_price;45050;Architect;120;125;;;\n"
            ."1001;Nieuwbouw Ijburg;Gemeente Amsterdam;yes;Schetsontwerp;fixed_price;45050;Tekenaar;80;;;;\n"
            ."1001;Nieuwbouw Ijburg;Gemeente Amsterdam;yes;Schetsontwerp;fixed_price;45050;;;;Inhuur derden;1;20000\n"
            ."1001;Nieuwbouw Ijburg;Gemeente Amsterdam;yes;Technisch ontwerp;after_calculation;;Bouwkundige;290;100;;;\n"
            ."1002;Intern werk;;no;Interne werkzaamheden;after_calculation;;;;;;;\n"
            ."1003;Onbekende klant;Bestaat Niet B.V.;yes;Dienst;fixed_price;100;;;;;;\n"
            ."1004;Kapot;;yes;Dienst;bogus;;;;;;;\n";

        $this->actingAs($admin)->post(route('projects.import.store'), ['file' => UploadedFile::fake()->createWithContent('projecten.csv', $csv)])
            ->assertRedirect(route('projects.import'))
            ->assertSessionHas('import_result', fn (array $r) => $r['created'] === 2 && $r['services'] === 3 && count($r['errors']) === 2);

        $project = Project::where('number', '1001')->with(Project::BUDGET_RELATIONS)->firstOrFail();
        $this->assertCount(2, $project->services);
        $this->assertSame(490.0, $project->budgetHours());
        $this->assertSame(20000.0, $project->budgetCosts());
        $this->assertSame(45050.0, $project->services->first()->price);
        $this->assertSame(95.0, (float) HourType::where('name', 'Tekenaar')->value('default_rate'), 'Rate falls back to the hour type default.');
        $this->assertFalse(Project::where('number', '1002')->firstOrFail()->is_invoiceable);

        $this->actingAs($admin)->post(route('projects.import.store'), ['file' => UploadedFile::fake()->createWithContent('projecten.csv', $csv)])
            ->assertSessionHas('import_result', fn (array $r) => $r['created'] === 0 && count($r['skipped']) >= 2);
        $this->assertSame(2, Project::count(), 'Re-importing must not duplicate projects.');

        $csvExport = $this->actingAs($admin)->get(route('projects.export', ['format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('Nieuwbouw Ijburg', $csvExport);

        $workbook = $this->actingAs($admin)->get(route('projects.export'))->assertOk();
        $zip = new \ZipArchive;
        $zip->open($workbook->baseResponse->getFile()->getPathname());
        preg_match_all('/<sheet name="([^"]+)"/', $zip->getFromName('xl/workbook.xml'), $matches);
        $this->assertSame(['Projecten', 'Diensten', 'Urenbudget', 'Kostenbudget', 'Geregistreerde uren', 'Inkoop'], $matches[1]);
        $this->assertStringContainsString('Nieuwbouw Ijburg', $zip->getFromName('xl/worksheets/sheet1.xml'));

        $this->actingAs($admin)->get(route('projects.import'))->assertOk();
        $this->actingAs($admin)->get(route('projects.import.template'))->assertOk();
    }

    public function test_pages_render(): void
    {
        $admin = $this->admin();
        $project = $this->projectWithBudget();
        $service = $project->services()->firstOrFail();

        foreach ([route('projects.index'), route('projects.index', ['tab' => 'all']), route('projects.show', $project), route('projects.show', [$project, 'tab' => 'hours']),
            route('projects.services.create', $project), route('projects.services.edit', [$project, $service]), route('purchases.index'), route('purchases.index', ['tab' => 'processed']),
            route('purchases.create', ['project_id' => $project->id]), route('project-settings.index'), route('notifications.index')] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
