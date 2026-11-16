<?php

namespace Tests\Feature;

use App\Enums\BalanceKind;
use App\Models\CustomerGroup;
use App\Models\Discipline;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Team;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimplicateParityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['user_group_id' => UserGroup::where('name', 'Beheerder')->value('id')]);
    }

    private function weekdaySchedule(Employee $employee, int $days = 5, string $end = '17:00'): void
    {
        $day = ['start' => '09:00', 'end' => $end, 'break' => 0];
        $employee->schedules()->create(['effective_from' => '2020-01-01', 'alternating' => false, 'pattern' => ['odd' => array_fill_keys(range(1, $days), $day)]]);
    }

    public function test_timeline_records_events_and_messages_with_attachments(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('organizations.store'), ['name' => 'Acme B.V.', 'type' => 'customer'])->assertRedirect();
        $organization = Organization::firstOrFail();
        $this->assertStringContainsString('Bedrijf aangemaakt', $organization->notes()->firstOrFail()->body);

        $this->actingAs($user)->post(route('organizations.notes.store', $organization), [
            'body' => 'Offerte besproken', 'type' => 'meeting', 'attachment' => UploadedFile::fake()->create('offerte.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $note = $organization->notes()->where('type', 'meeting')->firstOrFail();
        Storage::disk('local')->assertExists($note->attachment_path);
        $this->assertSame('offerte.pdf', $note->attachment_name);
        $this->actingAs($user)->get(route('notes.attachment', $note))->assertOk();

        $event = $organization->notes()->where('type', 'event')->firstOrFail();
        $this->actingAs($user)->delete(route('notes.destroy', $event))->assertForbidden();
        $this->actingAs($user)->delete(route('notes.destroy', $note))->assertRedirect();
        Storage::disk('local')->assertMissing($note->attachment_path);

        $this->actingAs($user)->get(route('organizations.show', $organization))->assertOk()->assertSee('Bedrijf aangemaakt');
    }

    public function test_person_actions_land_on_the_timeline(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $this->actingAs($user)->post(route('people.store'), ['first_name' => 'Jan', 'last_name' => 'Boer', 'note' => 'Eerste contact'])->assertRedirect();
        $person = Person::firstOrFail();
        $this->actingAs($user)->post(route('links.store'), ['organization_id' => $organization->id, 'person_id' => $person->id, 'job_title' => 'Directeur']);

        $bodies = $person->notes()->pluck('body')->implode(' | ');
        $this->assertStringContainsString('Persoon aangemaakt', $bodies);
        $this->assertStringContainsString('Eerste contact', $bodies);
        $this->assertStringContainsString($organization->name, $bodies);
        $this->assertStringContainsString('Directeur', $organization->notes()->firstOrFail()->body);
    }

    public function test_employees_are_people_and_can_be_filtered_as_such(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['first_name' => 'Sanne', 'last_name' => 'Medewerker', 'email' => 'sanne@bedrijf.nl', 'date_of_birth' => '1990-01-01']);
        $contact = Person::factory()->create(['first_name' => 'Kees', 'last_name' => 'Contact']);
        $contact->organizations()->attach(Organization::factory()->create()->id, ['job_title' => 'Inkoper', 'is_primary' => true]);

        $person = $employee->fresh()->person;
        $this->assertNotNull($person);
        $this->assertSame('sanne@bedrijf.nl', $person->email);
        $this->assertNull($person->date_of_birth, 'Private data must not leak into the CRM person.');

        $employee->update(['last_name' => 'Gewijzigd']);
        $this->assertSame('Gewijzigd', $person->fresh()->last_name);
        $this->assertSame(2, Person::count());

        $this->actingAs($user)->get(route('people.index', ['kind' => 'employee']))->assertSee('Sanne')->assertDontSee('Kees');
        $this->actingAs($user)->get(route('people.index', ['kind' => 'contact']))->assertSee('Kees')->assertDontSee('Sanne')->assertSee('Inkoper');
        $this->actingAs($user)->get(route('people.index'))->assertSee('Sanne')->assertSee('Kees');
    }

    public function test_new_employee_can_be_based_on_an_existing_person(): void
    {
        $person = Person::factory()->create(['first_name' => 'Piet', 'last_name' => 'Peters', 'email' => 'piet@bedrijf.nl']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('employees.create', ['person_id' => $person->id]))->assertOk()->assertSee('piet@bedrijf.nl');

        $this->actingAs($admin)->post(route('employees.store'), [
            'first_name' => 'Piet', 'last_name' => 'Peters', 'email' => 'piet@bedrijf.nl', 'person_id' => $person->id,
        ])->assertRedirect();

        $this->assertSame(1, Person::count());
        $this->assertSame($person->id, Employee::firstOrFail()->person_id);
    }

    public function test_employee_can_have_several_teams_and_disciplines_and_a_type(): void
    {
        $admin = $this->admin();
        [$a, $b] = Team::factory(2)->create();
        $design = Discipline::create(['name' => 'Design']);

        $this->actingAs($admin)->post(route('employees.store'), [
            'first_name' => 'Sanne', 'last_name' => 'Dijk', 'email' => 's@bedrijf.nl', 'employee_type' => 'external',
            'team_ids' => [$a->id, $b->id], 'discipline_ids' => [$design->id], 'marital_status' => 'married', 'private_phone' => '058 123 4567',
        ])->assertSessionHasNoErrors();

        $employee = Employee::firstOrFail();
        $this->assertCount(2, $employee->teams);
        $this->assertSame('external', $employee->employee_type->value);
        $this->assertSame('married', $employee->marital_status);

        $this->actingAs($admin)->put(route('employees.update', $employee), [
            'first_name' => 'Sanne', 'last_name' => 'Dijk', 'email' => 's@bedrijf.nl', 'team_ids' => [$b->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame([$b->id], $employee->fresh()->teams->pluck('id')->all());
        $this->assertSame('external', $employee->fresh()->employee_type->value);

        $this->actingAs($admin)->get(route('employees.index', ['team_id' => [$b->id], 'employee_type' => ['external']]))->assertSee('Sanne');
        $this->actingAs($admin)->get(route('employees.index', ['team_id' => [$a->id]]))->assertDontSee('Sanne');
    }

    public function test_employee_photo_is_stored_privately_and_served(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('employees.store'), [
            'first_name' => 'Foto', 'last_name' => 'Persoon', 'email' => 'foto@bedrijf.nl', 'photo' => UploadedFile::fake()->image('me.jpg'),
        ])->assertSessionHasNoErrors();

        $employee = Employee::firstOrFail();
        Storage::disk('local')->assertExists($employee->photo_path);
        $this->actingAs($admin)->get(route('employees.photo', $employee))->assertOk();
    }

    public function test_leave_summary_splits_current_balance_from_planned_leave_and_converts_to_days(): void
    {
        $employee = Employee::factory()->create();
        $this->weekdaySchedule($employee, 4, '15:00');
        $employee->ledgerEntries()->create(['kind' => 'vacation', 'reason' => 'allocation', 'hours' => 100, 'entry_date' => today()->subMonth()]);
        $employee->ledgerEntries()->create(['kind' => 'vacation', 'reason' => 'taken', 'hours' => -12, 'entry_date' => today()->addDay()]);
        $employee->load('schedules', 'ledgerEntries');

        $this->assertSame(100.0, $employee->balanceAsOf(BalanceKind::Vacation, today()));
        $this->assertSame(12.0, $employee->plannedHours(BalanceKind::Vacation));
        $this->assertSame(6.0, $employee->hoursPerDay());
        $this->assertSame(2.0, $employee->toDays(12));
    }

    public function test_absence_stats_report_hours_and_percentage(): void
    {
        $employee = Employee::factory()->create();
        $this->weekdaySchedule($employee);
        $year = now()->year - 1;
        $employee->absences()->create(['type' => 'sick', 'start_date' => "{$year}-03-03", 'end_date' => "{$year}-03-07", 'percentage' => 50]);
        $employee->load('schedules', 'absences');

        $stats = $employee->absenceStats($year);

        $this->assertSame(20.0, $stats['hours']);
        $this->assertGreaterThan(0, $stats['percentage']);
        $this->assertLessThan(2, $stats['percentage']);
    }

    public function test_leave_report_shows_begin_granted_taken_planned_and_end_balance_in_hours_or_days(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create(['hired_at' => '2020-01-01']);
        $this->weekdaySchedule($employee);
        $year = now()->year;
        $entries = [
            ['hours' => 40, 'entry_date' => ($year - 1).'-12-01', 'reason' => 'allocation'],
            ['hours' => 200, 'entry_date' => "{$year}-01-01", 'reason' => 'allocation'],
            ['hours' => -16, 'entry_date' => "{$year}-01-15", 'reason' => 'taken'],
            ['hours' => -8, 'entry_date' => "{$year}-12-31", 'reason' => 'taken'],
        ];
        foreach ($entries as $entry) {
            $employee->ledgerEntries()->create($entry + ['kind' => 'vacation']);
        }

        $csv = fn (array $query) => $this->actingAs($admin)->get(route('reports.leave.export', ['year' => $year, 'format' => 'csv'] + $query))->streamedContent();

        $hours = $csv([]);
        $this->assertStringContainsString('Saldo begin jaar (uren)', $hours);
        $this->assertStringContainsString(';40;200;16;8;216;', $hours);

        $days = $csv(['unit' => 'days']);
        $this->assertStringContainsString('Saldo einde jaar (dagen)', $days);
        $this->assertStringContainsString(';5;25;2;1;27;', $days);

        $this->actingAs($admin)->get(route('reports.leave', ['year' => $year, 'unit' => 'days']))->assertOk()->assertSee('Saldo in dagen');
    }

    public function test_crm_documents_can_be_uploaded_downloaded_and_removed(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $person = Person::factory()->create();

        $this->actingAs($user)->post(route('organizations.documents.store', $organization), ['file' => UploadedFile::fake()->create('offerte.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('people.documents.store', $person), ['title' => 'CV', 'file' => UploadedFile::fake()->create('cv.docx', 20)])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('people.documents.store', $person), ['file' => UploadedFile::fake()->create('script.php', 1)])->assertSessionHasErrors('file');

        $document = $organization->documents()->firstOrFail();
        $this->assertSame('offerte', $document->title);
        $this->assertSame('documents', $document->extensionGroup());
        Storage::disk('local')->assertExists($document->path);

        $this->actingAs($user)->get(route('documents.show', $document))->assertOk();
        $this->actingAs($user)->get(route('organizations.show', [$organization, 'tab' => 'documents']))->assertOk()->assertSee('offerte');
        $this->actingAs($user)->delete(route('documents.destroy', $document))->assertRedirect();
        Storage::disk('local')->assertMissing($document->path);
        $this->assertSame('CV', $person->documents()->firstOrFail()->title);
    }

    public function test_chart_preferences_are_saved_per_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Organization::factory()->create(['visit_country' => 'België']);

        $this->actingAs($user)->get(route('organizations.index'))->assertOk();
        $this->actingAs($user)->patch(route('preferences.organization-charts'), ['charts' => ['country', 'type', 'bogus']])->assertSessionHasErrors('charts.2');
        $this->actingAs($user)->patch(route('preferences.organization-charts'), ['charts' => ['country', 'type']])->assertRedirect();

        $this->assertSame(['type', 'country'], $user->fresh()->preferences['organization_charts']);
        $this->assertNull($other->fresh()->preferences);
        $this->actingAs($user)->get(route('organizations.index'))->assertOk()->assertSee('Weergave');
    }

    public function test_invoicing_preferences_follow_the_simplicate_options(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->create();
        $group = CustomerGroup::create(['name' => 'Key accounts']);
        $team = Team::factory()->create();

        $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'Factuur B.V.', 'type' => 'customer', 'invoice_attention' => 'contact_person', 'invoice_contact_person_id' => $person->id,
            'invoice_delivery' => 'email', 'payment_term_days' => 14, 'default_vat_code' => 'vat_21', 'is_vat_exempt' => 1,
            'iban' => 'nl91 abna 0417 1643 00', 'invoice_template' => 'detailed', 'reminder_plan' => 'strict',
            'use_reminder_email' => 1, 'reminder_email' => 'herinnering@factuur.nl', 'customer_group_id' => $group->id, 'team_id' => $team->id,
        ])->assertSessionHasNoErrors();

        $organization = Organization::firstOrFail();
        $this->assertSame('contact_person', $organization->invoice_attention->value);
        $this->assertTrue($organization->is_vat_exempt);
        $this->assertSame('herinnering@factuur.nl', $organization->reminder_email);
        $this->assertSame($group->id, $organization->customer_group_id);

        $this->actingAs($user)->put(route('organizations.update', $organization), [
            'name' => 'Factuur B.V.', 'type' => 'customer', 'payment_term_days' => 14, 'reminder_email' => 'ongebruikt@factuur.nl',
        ])->assertSessionHasNoErrors();
        $this->assertNull($organization->fresh()->reminder_email, 'The alternative address is only kept while its checkbox is ticked.');
        $this->assertSame('none', $organization->fresh()->invoice_attention->value);

        $this->actingAs($user)->post(route('organizations.store'), ['name' => 'Fout', 'type' => 'customer', 'payment_term_days' => 13])->assertSessionHasErrors('payment_term_days');

        $this->actingAs($user)->get(route('organizations.show', [$organization, 'tab' => 'billing']))->assertOk()->assertSee('14 dagen');
        $this->actingAs($user)->get(route('debtors.index'))->assertOk()->assertSee('Factuur B.V.');
    }

    public function test_customer_groups_can_be_managed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('customer-groups.store'), ['name' => 'Key accounts'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('customer-groups.store'), ['name' => 'Key accounts'])->assertSessionHasErrors('name');

        $group = CustomerGroup::firstOrFail();
        $this->actingAs($user)->put(route('customer-groups.update', $group), ['name' => 'Grootklanten'])->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('customer-groups.index'))->assertOk()->assertSee('Grootklanten');
        $this->actingAs($user)->delete(route('customer-groups.destroy', $group));
        $this->assertSame(0, CustomerGroup::count());
    }

    public function test_person_can_have_extra_fields_and_a_custom_salutation(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $this->actingAs($user)->post(route('people.store'), [
            'first_name' => 'Jan', 'last_name' => 'Boer', 'twitter_url' => 'https://x.com/jan', 'facebook_url' => 'https://facebook.com/jan',
            'use_salutation' => 1, 'salutation' => 'Beste Jan,', 'team_id' => $team->id, 'relation_manager_id' => $user->id,
        ])->assertSessionHasNoErrors();

        $person = Person::firstOrFail();
        $this->assertSame('Beste Jan,', $person->salutation);
        $this->assertSame($team->id, $person->team_id);

        $this->actingAs($user)->put(route('people.update', $person), ['first_name' => 'Jan', 'last_name' => 'Boer', 'salutation' => 'Hoi'])->assertSessionHasNoErrors();
        $this->assertNull($person->fresh()->salutation);

        $this->actingAs($user)->get(route('people.show', $person))->assertOk()->assertSee('x.com/jan');
    }

    public function test_company_list_can_be_filtered_by_team_and_customer_group(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $group = CustomerGroup::create(['name' => 'Retail']);
        Organization::factory()->create(['name' => 'In Team B.V.', 'team_id' => $team->id, 'customer_group_id' => $group->id]);
        Organization::factory()->create(['name' => 'Buiten Team B.V.']);

        $this->actingAs($user)->get(route('organizations.index', ['team_id' => [$team->id]]))->assertSee('In Team')->assertDontSee('Buiten Team');
        $this->actingAs($user)->get(route('organizations.index', ['customer_group_id' => [$group->id]]))->assertSee('In Team')->assertDontSee('Buiten Team');
    }
}
