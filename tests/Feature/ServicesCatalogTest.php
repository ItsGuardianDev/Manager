<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\CostType;
use App\Models\DefaultService;
use App\Models\Employee;
use App\Models\HourType;
use App\Models\Organization;
use App\Models\Project;
use App\Models\RevenueGroup;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ServicesCatalogTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Haalbaarheidsstudie', 'invoice_method' => 'fixed_price', 'price' => 12500, 'price_editable' => 1, 'vat_code' => 'vat_21',
            'revenue_group_id' => RevenueGroup::first()->id, 'allow_hours' => 1, 'allow_costs' => 1, 'is_plannable' => 1,
            'description' => 'Standaardtekst', 'internal_note' => 'Alleen intern',
            'hour_lines' => [['hour_type_id' => HourType::where('name', 'Architect')->value('id'), 'hours' => 40, 'rate' => ''], ['hour_type_id' => '', 'hours' => '', 'rate' => '']],
            'cost_lines' => [['cost_type_id' => CostType::where('name', 'Materiaal')->value('id'), 'quantity' => 2, 'purchase_price' => 100, 'margin_percentage' => '']],
            ...$overrides,
        ];
    }

    public function test_catalogue_requires_login_and_permission(): void
    {
        $this->get(route('services.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('services.index'))->assertForbidden();

        $viewer = $this->userWith([Permission::ProjectsView]);
        $this->actingAs($viewer)->get(route('services.index'))->assertOk()->assertSee('Schetsontwerp');
        $this->actingAs($viewer)->get(route('services.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('services.store'), $this->payload())->assertForbidden();
    }

    public function test_service_is_created_with_settings_budgets_and_defaults(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('services.store'), $this->payload())->assertSessionHasNoErrors();

        $service = DefaultService::where('name', 'Haalbaarheidsstudie')->with(['hourLines', 'costLines'])->firstOrFail();
        $this->assertSame(12500.0, $service->price);
        $this->assertTrue($service->allow_hours && $service->allow_costs && $service->is_plannable);
        $this->assertSame('vat_21', $service->vat_code);
        $this->assertSame('Alleen intern', $service->internal_note);
        $this->assertCount(1, $service->hourLines);
        $this->assertSame(125.0, $service->hourLines->first()->rate, 'Empty rate falls back to the hour type default.');
        $this->assertSame(15.0, $service->costLines->first()->margin_percentage, 'Empty margin falls back to the cost type default.');

        $this->actingAs($admin)->post(route('services.store'), $this->payload(['name' => 'Haalbaarheidsstudie']))->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('services.store'), $this->payload(['name' => 'Nacalculatie', 'invoice_method' => 'after_calculation']));
        $this->assertNull(DefaultService::where('name', 'Nacalculatie')->value('price'), 'Only fixed price and subscription services have a price.');

        $this->actingAs($admin)->post(route('services.store'), $this->payload(['name' => 'Beheer', 'invoice_method' => 'subscription', 'price' => 300]));
        $this->assertSame('monthly', DefaultService::where('name', 'Beheer')->firstOrFail()->billing_cycle->value);
        $this->actingAs($admin)->post(route('services.store'), $this->payload(['name' => 'Onderhoud', 'invoice_method' => 'subscription', 'billing_cycle' => 'quarterly']));
        $this->assertSame('quarterly', DefaultService::where('name', 'Onderhoud')->firstOrFail()->billing_cycle->value);

        $this->actingAs($admin)->put(route('services.update', $service), $this->payload(['allow_hours' => 0, 'hour_lines' => []]))->assertSessionHasNoErrors();
        $service->refresh()->load('hourLines');
        $this->assertFalse($service->allow_hours);
        $this->assertCount(0, $service->hourLines);
    }

    public function test_overview_searches_and_filters_by_status(): void
    {
        $admin = $this->admin();
        DefaultService::where('name', 'Voorontwerp')->update(['is_blocked' => true]);

        $this->actingAs($admin)->get(route('services.index'))->assertSee('Schetsontwerp')->assertDontSee('Voorontwerp');
        $this->actingAs($admin)->get(route('services.index', ['status' => 'blocked']))->assertSee('Voorontwerp')->assertDontSee('Schetsontwerp');
        $this->actingAs($admin)->get(route('services.index', ['status' => 'all']))->assertSee('Voorontwerp')->assertSee('Schetsontwerp');
        $this->actingAs($admin)->get(route('services.index', ['q' => 'Schets']))->assertSee('Schetsontwerp')->assertDontSee('Uitvoering');
    }

    public function test_blocked_services_are_unavailable_for_new_project_services_but_history_stays(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $default = DefaultService::where('name', 'Schetsontwerp')->firstOrFail();

        $this->actingAs($admin)->post(route('projects.services.store', $project), ['name' => 'Schetsontwerp', 'invoice_method' => 'after_calculation', 'default_service_id' => $default->id])->assertSessionHasNoErrors();
        $existing = $project->services()->firstOrFail();

        $this->actingAs($admin)->patch(route('services.toggle-blocked', $default))->assertSessionHas('status');
        $this->assertTrue($default->fresh()->is_blocked);

        $this->actingAs($admin)->get(route('projects.services.create', $project))->assertDontSee('>Schetsontwerp</option>', false);
        $this->actingAs($admin)->post(route('projects.services.store', $project), ['name' => 'Nog een', 'invoice_method' => 'after_calculation', 'default_service_id' => $default->id])->assertSessionHasErrors('default_service_id');

        $this->actingAs($admin)->put(route('projects.services.update', [$project, $existing]), ['name' => 'Schetsontwerp 2', 'invoice_method' => 'after_calculation', 'default_service_id' => $default->id])->assertSessionHasNoErrors();
        $this->assertSame($default->id, $existing->fresh()->default_service_id, 'Existing project services keep their link to a blocked service.');
        $this->actingAs($admin)->get(route('projects.services.edit', [$project, $existing]))->assertOk()->assertSee('inmiddels geblokkeerd');

        $this->actingAs($admin)->patch(route('services.toggle-blocked', $default));
        $this->assertFalse($default->fresh()->is_blocked);
    }

    public function test_adding_a_standard_service_to_a_project_prefills_it_and_can_lock_the_price(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->actingAs($admin)->post(route('services.store'), $this->payload(['price_editable' => 0]));
        $default = DefaultService::where('name', 'Haalbaarheidsstudie')->firstOrFail();

        $this->actingAs($admin)->get(route('projects.services.create', [$project, 'default_service' => $default->id]))
            ->assertOk()->assertSee('Haalbaarheidsstudie')->assertSee('12500')->assertSee('Standaardtekst')->assertSee('kan niet worden aangepast');

        $this->actingAs($admin)->post(route('projects.services.store', $project), [
            'name' => 'Haalbaarheidsstudie', 'invoice_method' => 'fixed_price', 'price' => 1, 'default_service_id' => $default->id,
            'hour_lines' => [['hour_type_id' => HourType::where('name', 'Architect')->value('id'), 'hours' => 55, 'rate' => 130]],
        ])->assertSessionHasNoErrors();

        $service = $project->services()->with('hourLines')->firstOrFail();
        $this->assertSame(12500.0, $service->price, 'A locked standard price cannot be changed per project.');
        $this->assertSame(55.0, $service->budgetHours(), 'Budgets can be adjusted per project.');

        $this->actingAs($admin)->put(route('services.update', $default), $this->payload(['price_editable' => 1]));
        $this->actingAs($admin)->put(route('projects.services.update', [$project, $service]), ['name' => 'Haalbaarheidsstudie', 'invoice_method' => 'fixed_price', 'price' => 9000, 'default_service_id' => $default->id, 'end_date' => '2027-01-31', 'description' => 'Aangepast']);
        $service->refresh();
        $this->assertSame(9000.0, $service->price);
        $this->assertSame('2027-01-31', $service->end_date->format('Y-m-d'));
        $this->assertSame(12500.0, $default->fresh()->price, 'Changing a project service never touches the catalogue.');
    }

    public function test_services_can_block_hours_and_costs(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create(['user_id' => $admin->id]);
        $project = Project::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $service = $project->services()->create(['name' => 'Alleen kosten', 'invoice_method' => 'after_calculation', 'allow_hours' => false, 'sort_order' => 1]);

        $this->actingAs($admin)->post(route('projects.hours.store', $project), ['employee_id' => $employee->id, 'project_service_id' => $service->id, 'date' => today()->format('Y-m-d'), 'hours' => 2])->assertSessionHasErrors('project_service_id');
        $this->assertSame(0, $project->timeEntries()->count());

        $service->update(['allow_hours' => true, 'allow_costs' => false]);
        $this->actingAs($admin)->post(route('purchases.store'), ['project_id' => $project->id, 'date' => today()->format('Y-m-d'), 'description' => 'Inkoop', 'type' => 'invoice', 'lines' => [['project_service_id' => $service->id, 'unit_price' => 50]]])->assertSessionHasErrors('lines');
        $this->assertSame(0, $project->purchases()->count());

        $service->update(['allow_costs' => true]);
        $this->actingAs($admin)->post(route('purchases.store'), ['project_id' => $project->id, 'date' => today()->format('Y-m-d'), 'description' => 'Inkoop', 'type' => 'invoice', 'lines' => [['project_service_id' => $service->id, 'unit_price' => 50]]])->assertSessionHasNoErrors();
        $this->assertSame(1, $project->purchases()->count());
    }

    public function test_services_in_use_cannot_be_deleted_but_unused_ones_can(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $used = DefaultService::where('name', 'Uitvoering')->firstOrFail();
        $project->services()->create(['name' => 'Uitvoering', 'invoice_method' => 'after_calculation', 'default_service_id' => $used->id, 'sort_order' => 1]);

        $this->actingAs($admin)->delete(route('services.destroy', $used))->assertSessionHasErrors('service');
        $this->assertNotNull($used->fresh());

        $this->actingAs($admin)->delete(route('services.destroy', DefaultService::where('name', 'Meerwerk')->firstOrFail()))->assertRedirect(route('services.index'));
        $this->assertNull(DefaultService::where('name', 'Meerwerk')->first());
    }

    public function test_services_can_be_imported_updated_and_exported(): void
    {
        $admin = $this->admin();
        $csv = "name;description;invoice_method;price;billing_cycle;vat_code;revenue_group;allow_hours;allow_costs;is_plannable;is_blocked;hour_type;hours;hourly_rate;cost_type;cost_quantity;purchase_price\n"
            ."Importdienst;Uit Excel;fixed_price;5000;;vat_9;Nieuwe groep;yes;no;yes;no;Architect;10;130;;;\n"
            ."Importdienst;Uit Excel;fixed_price;5000;;vat_9;Nieuwe groep;yes;no;yes;no;Jurist;5;;;;\n"
            ."Abonnement;;subscription;99;yearly;;;yes;yes;no;yes;;;;Licenties;1;99\n"
            ."Kapot;;bogus;;;;;;;;;;;;;;\n";

        $this->actingAs($admin)->post(route('services.import.store'), ['file' => UploadedFile::fake()->createWithContent('diensten.csv', $csv)])
            ->assertRedirect(route('services.import'))
            ->assertSessionHas('import_result', fn (array $r) => $r['created'] === 2 && $r['updated'] === 0 && count($r['errors']) === 1);

        $service = DefaultService::where('name', 'Importdienst')->with('hourLines', 'revenueGroup')->firstOrFail();
        $this->assertSame(5000.0, $service->price);
        $this->assertFalse($service->allow_costs);
        $this->assertSame('Nieuwe groep', $service->revenueGroup->name);
        $this->assertSame(15.0, $service->budgetHours());
        $this->assertSame(130.0, $service->hourLines->first()->rate);
        $this->assertNotNull(HourType::where('name', 'Jurist')->first(), 'Unknown hour types are created.');

        $subscription = DefaultService::where('name', 'Abonnement')->firstOrFail();
        $this->assertSame('yearly', $subscription->billing_cycle->value);
        $this->assertTrue($subscription->is_blocked);
        $this->assertFalse($subscription->is_plannable);

        $this->actingAs($admin)->post(route('services.import.store'), ['file' => UploadedFile::fake()->createWithContent('diensten.csv', $csv)])
            ->assertSessionHas('import_result', fn (array $r) => $r['created'] === 0 && $r['updated'] === 2);
        $this->assertSame(1, DefaultService::where('name', 'Importdienst')->count());
        $this->assertSame(2, $service->fresh()->hourLines()->count(), 'Re-importing replaces the lines instead of doubling them.');

        $csvExport = $this->actingAs($admin)->get(route('services.export', ['format' => 'csv', 'status' => 'all']))->streamedContent();
        $this->assertStringContainsString('Importdienst', $csvExport);

        $workbook = $this->actingAs($admin)->get(route('services.export', ['status' => 'all']))->assertOk();
        $zip = new \ZipArchive;
        $zip->open($workbook->baseResponse->getFile()->getPathname());
        preg_match_all('/<sheet name="([^"]+)"/', $zip->getFromName('xl/workbook.xml'), $matches);
        $this->assertSame(['Diensten', 'Urensoorten', 'Kostensoorten'], $matches[1]);

        $this->actingAs($admin)->get(route('services.import.template'))->assertOk();
        $this->actingAs($admin)->get(route('services.import'))->assertOk();
    }

    public function test_service_pages_render(): void
    {
        $admin = $this->admin();
        $default = DefaultService::where('name', 'Schetsontwerp')->firstOrFail();

        $this->actingAs($admin)->get(route('services.create'))->assertOk()->assertSee('Omzetgroep')->assertSee('Facturatieperiode');
        $this->actingAs($admin)->get(route('services.edit', $default))->assertOk()->assertSee('Urensoorten');
        $this->actingAs($admin)->get(route('project-settings.index'))->assertOk()->assertDontSee('Standaarddiensten</h2>', false);
    }
}
