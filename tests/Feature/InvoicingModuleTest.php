<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Models\Employee;
use App\Models\HourType;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\InvoiceBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicingModuleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['user_group_id' => UserGroup::where('name', 'Beheerder')->value('id')]);
    }

    private function project(): Project
    {
        return Project::factory()->create(['organization_id' => Organization::factory()->create(['payment_term_days' => 30, 'type' => 'customer'])->id]);
    }

    private function hoursService(Project $project, string $status = 'approved', float $hours = 4): ProjectService
    {
        $service = $project->services()->create(['name' => 'Ontwerp', 'invoice_method' => 'after_calculation', 'sort_order' => 1]);
        TimeEntry::create([
            'employee_id' => Employee::factory()->create()->id, 'project_id' => $project->id, 'project_service_id' => $service->id,
            'hour_type_id' => HourType::where('name', 'Architect')->value('id'), 'date' => today(), 'hours' => $hours, 'rate' => 100, 'status' => $status,
        ]);

        return $service;
    }

    private function draftFor(Project $project): Invoice
    {
        $this->actingAs($this->admin())->post(route('invoices.from-proposal', $project), ['keys' => collect(app(InvoiceBuilder::class)->proposals($project))->where('ready', true)->pluck('key')->all()]);

        return Invoice::firstOrFail();
    }

    public function test_invoicing_requires_login_and_permissions(): void
    {
        $this->get(route('invoices.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('invoices.index'))->assertForbidden();

        $viewer = User::factory()->create(['user_group_id' => UserGroup::factory()->with([Permission::InvoicesView])->create()->id]);
        $this->actingAs($viewer)->get(route('invoices.index'))->assertOk();
        $this->actingAs($viewer)->post(route('invoices.store'), [])->assertForbidden();
        $this->assertContains('invoices.manage', UserGroup::where('name', 'Beheerder')->firstOrFail()->permissions);
    }

    public function test_ready_overview_separates_approved_from_unapproved_hours(): void
    {
        $project = $this->project();
        $this->hoursService($project, 'approved', 4);
        $project->services()->first()->timeEntries()->create([
            'employee_id' => Employee::factory()->create()->id, 'project_id' => $project->id, 'hour_type_id' => HourType::where('name', 'Architect')->value('id'), 'date' => today(), 'hours' => 2, 'rate' => 100, 'status' => 'submitted',
        ]);

        $this->actingAs($this->admin())->get(route('invoices.index'))->assertOk()->assertSee($project->name)->assertSee('400,00')->assertSee('200,00');
        $this->actingAs($this->admin())->get(route('invoices.proposal', $project))->assertOk()->assertSee('Uren nog niet goedgekeurd');
    }

    public function test_draft_is_created_from_approved_hours_and_hours_are_reserved(): void
    {
        $project = $this->project();
        $this->hoursService($project);

        $invoice = $this->draftFor($project);

        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertNull($invoice->number);
        $this->assertSame(400.0, $invoice->load('lines')->subtotal());
        $this->assertSame(484.0, $invoice->total());
        $this->assertSame(now()->addDays(30)->toDateString(), $invoice->due_date->toDateString());
        $this->assertSame($invoice->id, TimeEntry::firstOrFail()->invoice_id);
        $this->assertSame([], app(InvoiceBuilder::class)->proposals($project));
    }

    public function test_unapproved_hours_cannot_be_invoiced(): void
    {
        $project = $this->project();
        $this->hoursService($project, 'submitted');
        $key = app(InvoiceBuilder::class)->proposals($project)[0]['key'];

        $this->actingAs($this->admin())->post(route('invoices.from-proposal', $project), ['keys' => [$key]])->assertSessionHasErrors('keys');
        $this->assertSame(0, Invoice::count());
    }

    public function test_deleting_a_draft_releases_the_hours(): void
    {
        $project = $this->project();
        $this->hoursService($project);
        $invoice = $this->draftFor($project);

        $this->actingAs($this->admin())->delete(route('invoices.destroy', $invoice))->assertRedirect();

        $this->assertNull(TimeEntry::firstOrFail()->invoice_id);
        $this->assertCount(1, app(InvoiceBuilder::class)->proposals($project));
    }

    public function test_sending_assigns_sequential_numbers_locks_hours_and_freezes_the_invoice(): void
    {
        $project = $this->project();
        $this->hoursService($project);
        $invoice = $this->draftFor($project);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('invoices.send', $invoice), ['method' => 'print'])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(now()->format('Y').'0001', $invoice->number);
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertNotNull(TimeEntry::firstOrFail()->invoiced_at);

        $this->actingAs($admin)->put(route('invoices.update', $invoice), ['invoice_date' => today()->toDateString(), 'due_date' => today()->toDateString()])->assertSessionHasErrors('invoice');
        $this->actingAs($admin)->delete(route('invoices.destroy', $invoice))->assertSessionHasErrors('invoice');

        $second = app(InvoiceBuilder::class)->newInvoice($project->organization_id, null, 'Los');
        $second->lines()->create(['description' => 'Advies', 'quantity' => 1, 'unit_price' => 50]);
        $this->actingAs($admin)->post(route('invoices.send', $second), ['method' => 'print']);
        $this->assertSame(now()->format('Y').'0002', $second->refresh()->number);
    }

    public function test_an_invoice_without_lines_cannot_be_sent(): void
    {
        $project = $this->project();
        $invoice = app(InvoiceBuilder::class)->newInvoice($project->organization_id, null, 'Leeg');

        $this->actingAs($this->admin())->post(route('invoices.send', $invoice), ['method' => 'print'])->assertSessionHasErrors('invoice');
    }

    public function test_draft_lines_can_be_added_discounted_and_totals_include_vat(): void
    {
        $project = $this->project();
        $invoice = app(InvoiceBuilder::class)->newInvoice($project->organization_id, null, 'Los');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('invoices.lines.store', $invoice), ['description' => 'Advies', 'quantity' => 2, 'unit_price' => 100, 'vat_rate' => 21, 'discount_percentage' => 10])->assertRedirect();
        $this->actingAs($admin)->post(route('invoices.lines.store', $invoice), ['description' => 'Boek', 'quantity' => 1, 'unit_price' => 50, 'vat_rate' => 9])->assertRedirect();

        $invoice->load('lines');
        $this->assertSame(230.0, $invoice->subtotal());
        $this->assertSame(37.8 + 4.5, $invoice->vatTotal());
        $this->assertSame(272.3, $invoice->total());
        $this->assertSame(['21' => 37.8, '9' => 4.5], $invoice->vatBreakdown());
    }

    public function test_partial_and_full_payments_update_the_status(): void
    {
        $project = $this->project();
        $this->hoursService($project);
        $invoice = $this->draftFor($project);
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('invoices.send', $invoice), ['method' => 'print']);

        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice), ['amount' => 184, 'paid_on' => today()->toDateString()])->assertRedirect();
        $invoice->refresh()->load('lines', 'payments');
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertSame(300.0, $invoice->outstanding());

        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice), ['amount' => 300, 'paid_on' => today()->toDateString()]);
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
        $this->actingAs($admin)->get(route('invoices.index', ['tab' => 'outstanding']))->assertOk()->assertDontSee($invoice->number);
    }

    public function test_payments_cannot_be_registered_on_a_draft(): void
    {
        $project = $this->project();
        $this->hoursService($project);
        $invoice = $this->draftFor($project);

        $this->actingAs($this->admin())->post(route('invoices.payments.store', $invoice), ['amount' => 10, 'paid_on' => today()->toDateString()])->assertSessionHasErrors('amount');
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_credit_invoice_reverses_the_lines_and_only_works_on_sent_invoices(): void
    {
        $project = $this->project();
        $this->hoursService($project);
        $invoice = $this->draftFor($project);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('invoices.credit', $invoice))->assertSessionHasErrors('invoice');

        $this->actingAs($admin)->post(route('invoices.send', $invoice), ['method' => 'print']);
        $this->actingAs($admin)->post(route('invoices.credit', $invoice))->assertRedirect();

        $credit = Invoice::whereNotNull('credit_for_id')->firstOrFail()->load('lines');
        $this->assertSame($invoice->id, $credit->credit_for_id);
        $this->assertSame(-400.0, $credit->subtotal());
        $this->assertSame(-484.0, $credit->total());
        $this->actingAs($admin)->post(route('invoices.credit', $credit))->assertSessionHasErrors('invoice');
    }

    public function test_fixed_price_and_subscription_services_are_proposed_once_per_period(): void
    {
        $project = $this->project();
        $project->services()->create(['name' => 'Ontwerp vast', 'invoice_method' => 'fixed_price', 'price' => 1000, 'sort_order' => 1]);
        $project->services()->create(['name' => 'Onderhoud', 'invoice_method' => 'subscription', 'billing_cycle' => 'monthly', 'price' => 100, 'invoice_from' => today()->subDay(), 'sort_order' => 2]);
        $project->services()->create(['name' => 'Later', 'invoice_method' => 'fixed_price', 'price' => 500, 'invoice_from' => today()->addMonth(), 'sort_order' => 3]);
        $builder = app(InvoiceBuilder::class);

        $proposals = collect($builder->proposals($project));
        $this->assertSame(['Ontwerp vast', 'Later'], $proposals->where('type', 'fixed_price')->pluck('service')->all());
        $this->assertFalse($proposals->firstWhere('service', 'Later')['ready']);

        $this->actingAs($this->admin());
        $invoice = $builder->createDraft($project, $proposals->where('ready', true)->pluck('key')->all());

        $this->assertCount(2, $invoice->lines);
        $remaining = collect($builder->proposals($project));
        $this->assertSame(['Later'], $remaining->where('type', 'fixed_price')->pluck('service')->all());
        $this->assertTrue($remaining->firstWhere('type', 'subscription') === null || ! $remaining->firstWhere('type', 'subscription')['ready']);
    }

    public function test_hours_can_be_written_off_and_are_no_longer_proposed(): void
    {
        $project = $this->project();
        $this->hoursService($project);
        $key = app(InvoiceBuilder::class)->proposals($project)[0]['key'];

        $this->actingAs($this->admin())->post(route('invoices.write-off', $project), ['keys' => [$key]])->assertRedirect();

        $this->assertNotNull(TimeEntry::firstOrFail()->written_off_at);
        $this->assertSame([], app(InvoiceBuilder::class)->proposals($project));
    }

    public function test_lists_filter_by_search_and_overdue_and_export_works(): void
    {
        $project = $this->project();
        $this->hoursService($project);
        $invoice = $this->draftFor($project);
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('invoices.send', $invoice), ['method' => 'print']);
        $invoice->update(['due_date' => today()->subDays(5)]);

        $this->actingAs($admin)->get(route('invoices.index', ['tab' => 'outstanding', 'overdue' => 'late']))->assertOk()->assertSee($invoice->number);
        $this->actingAs($admin)->get(route('invoices.index', ['tab' => 'outstanding', 'overdue' => 'within']))->assertOk()->assertDontSee($invoice->number);
        $this->actingAs($admin)->get(route('invoices.index', ['tab' => 'all', 'q' => $project->organization->name]))->assertOk()->assertSee($invoice->number);
        $this->actingAs($admin)->get(route('invoices.index', ['tab' => 'all', 'q' => 'bestaat-niet']))->assertOk()->assertDontSee($invoice->number);
        $this->actingAs($admin)->get(route('invoices.export'))->assertOk();
        $this->actingAs($admin)->get(route('invoices.show', $invoice))->assertOk()->assertSee('Betaling registreren');
        $this->actingAs($admin)->get(route('invoices.print', $invoice))->assertOk()->assertSee('Factuur '.$invoice->number);
    }

    public function test_timeline_records_the_invoice_history(): void
    {
        $project = $this->project();
        $this->hoursService($project);
        $invoice = $this->draftFor($project);
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('invoices.send', $invoice), ['method' => 'print']);

        $this->actingAs($admin)->get(route('invoices.show', [$invoice, 'tab' => 'timeline']))->assertOk()->assertSee('Conceptfactuur aangemaakt')->assertSee('verstuurd');
    }

    public function test_project_organization_and_debtor_pages_show_invoice_figures(): void
    {
        $project = $this->project();
        $this->hoursService($project);
        $invoice = $this->draftFor($project);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('projects.index'))->assertOk()->assertSee('Gefactureerd');
        $this->assertSame(400.0, $project->refresh()->load('invoices.lines')->draftInvoiceAmount());
        $this->assertSame(0.0, $project->invoicedAmount());

        $this->actingAs($admin)->post(route('invoices.send', $invoice), ['method' => 'print']);
        $project->refresh()->load('invoices.lines', 'services.invoiceLines.invoice');
        $this->assertSame(400.0, $project->invoicedAmount());
        $this->assertSame(400.0, $project->services->first()->invoicedAmount());
        $this->assertSame(today()->toDateString(), $project->services->first()->lastInvoicedOn()->toDateString());

        $this->actingAs($admin)->get(route('projects.show', [$project, 'tab' => 'billing']))->assertOk()->assertSee($invoice->refresh()->number);
        $this->actingAs($admin)->get(route('organizations.show', [$project->organization, 'tab' => 'billing']))->assertOk()->assertSee($invoice->number)->assertSee('484,00');

        $organization = $project->organization->load('invoices.lines', 'invoices.payments');
        $this->assertSame(484.0, $organization->outstandingAmount());
        $this->assertSame(0.0, $organization->overdueAmount());
        $invoice->update(['due_date' => today()->subDays(3)]);
        $this->assertSame(484.0, $organization->refresh()->load('invoices.lines', 'invoices.payments')->overdueAmount());
        $this->actingAs($admin)->get(route('debtors.index'))->assertOk()->assertSee('484,00');
    }

    public function test_hours_report_lists_employees_without_hours_and_productivity(): void
    {
        $admin = $this->admin();
        $busy = Employee::factory()->create(['first_name' => 'Druk', 'last_name' => 'Bezig']);
        $idle = Employee::factory()->create(['first_name' => 'Stil', 'last_name' => 'Staand']);
        foreach ([$busy, $idle] as $employee) {
            $employee->schedules()->create(['effective_from' => '2020-01-01', 'alternating' => false, 'pattern' => ['odd' => array_fill_keys(range(1, 5), ['start' => '09:00', 'end' => '17:00', 'break' => 0])]]);
        }
        $project = $this->project();
        $service = $project->services()->create(['name' => 'Ontwerp', 'invoice_method' => 'after_calculation']);
        TimeEntry::create(['employee_id' => $busy->id, 'project_id' => $project->id, 'project_service_id' => $service->id, 'date' => '2026-11-16', 'hours' => 4, 'rate' => 100, 'status' => 'approved']);

        $response = $this->actingAs($admin)->get(route('hours.report', ['from' => '2026-11-16', 'to' => '2026-11-16']));

        $response->assertOk()->assertSee('Stil Staand')->assertSee('Druk Bezig')->assertSee('Productiviteit')->assertSee('Declarabiliteit')->assertSee('50%');
    }

    public function test_employees_can_be_filtered_on_system_access(): void
    {
        $withAccess = Employee::factory()->create(['user_id' => User::factory()->create(['is_active' => true])->id]);
        $blocked = Employee::factory()->create(['user_id' => User::factory()->create(['is_active' => false])->id]);
        $without = Employee::factory()->create(['user_id' => null]);

        $names = fn (array $access) => Employee::filter(['access' => $access, 'status' => 'all'])->pluck('id')->all();

        $this->assertSame([$withAccess->id], $names(['yes']));
        $this->assertSame([$blocked->id], $names(['blocked']));
        $this->assertSame([$without->id], $names(['no']));
    }

    public function test_revenue_report_groups_sent_invoices_per_month_and_credits_count_negative(): void
    {
        $project = $this->project();
        $this->hoursService($project);
        $invoice = $this->draftFor($project);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('invoices.report'))->assertOk()->assertSee('Geen verzonden facturen');

        $this->actingAs($admin)->post(route('invoices.send', $invoice), ['method' => 'print']);
        $this->actingAs($admin)->get(route('invoices.report'))->assertOk()->assertSee('Ontwerp')->assertSee('400,00');
        $this->actingAs($admin)->get(route('invoices.report', ['group' => 'debtor']))->assertOk()->assertSee($project->organization->name);
        $this->actingAs($admin)->get(route('invoices.report', ['year' => now()->year - 1]))->assertOk()->assertSee('Geen verzonden facturen');

        $this->actingAs($admin)->post(route('invoices.credit', $invoice));
        $credit = Invoice::whereNotNull('credit_for_id')->firstOrFail();
        $this->actingAs($admin)->post(route('invoices.send', $credit), ['method' => 'print']);
        $this->actingAs($admin)->get(route('invoices.report'))->assertOk()->assertDontSee('400,00');
        $this->actingAs($admin)->get(route('invoices.report', ['export' => 1]))->assertOk();
    }

    public function test_next_action_follows_the_reminder_schedule(): void
    {
        $project = $this->project();
        $this->hoursService($project);
        $invoice = $this->draftFor($project);
        $admin = $this->admin();

        $this->assertNull($invoice->load('lines', 'payments')->nextAction());

        $this->actingAs($admin)->post(route('invoices.send', $invoice), ['method' => 'print']);
        $invoice->refresh()->load('lines', 'payments');
        $this->assertSame('Eerste herinnering', $invoice->nextAction()['label']);
        $this->assertSame($invoice->due_date->copy()->addWeek()->toDateString(), $invoice->nextAction()['date']->toDateString());

        $this->actingAs($admin)->post(route('invoices.remind', $invoice));
        $this->assertSame('Tweede herinnering', $invoice->refresh()->load('lines', 'payments')->nextAction()['label']);

        $this->actingAs($admin)->patch(route('invoices.reminders', $invoice));
        $this->assertSame('Herinneringen gepauzeerd', $invoice->refresh()->load('lines', 'payments')->nextAction()['label']);
        $this->actingAs($admin)->get(route('invoices.index', ['tab' => 'outstanding']))->assertOk()->assertSee('Eerstvolgende actie')->assertSee('Herinneringen gepauzeerd');
    }
}
