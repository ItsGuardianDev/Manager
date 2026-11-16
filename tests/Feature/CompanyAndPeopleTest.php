<?php

namespace Tests\Feature;

use App\Models\Industry;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class CompanyAndPeopleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('organizations.index'))->assertRedirect(route('login'));
        $this->get(route('people.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_log_in_and_inactive_user_cannot(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        $this->post('login', ['email' => $user->email, 'password' => 'secret-pass'])->assertRedirect(route('organizations.index'));
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $user->update(['is_active' => false]);

        $this->post('login', ['email' => $user->email, 'password' => 'secret-pass'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_company_can_be_created_with_type_industry_manager_and_billing(): void
    {
        $user = User::factory()->create();
        $industry = Industry::create(['name' => 'Retail']);

        $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'Acme B.V.', 'type' => 'supplier', 'industry_id' => $industry->id,
            'relation_manager_id' => $user->id, 'iban' => 'nl91 abna 0417 1643 00', 'payment_term_days' => 14,
        ])->assertRedirect();

        $organization = Organization::firstOrFail();
        $this->assertSame('supplier', $organization->type->value);
        $this->assertSame('NL91ABNA0417164300', $organization->iban);
        $this->assertSame($user->id, $organization->relation_manager_id);
    }

    public function test_duplicate_companies_are_rejected(): void
    {
        $user = User::factory()->create();
        Organization::factory()->create(['name' => 'Acme B.V.', 'coc_number' => '123', 'vat_number' => null]);

        $this->actingAs($user)->post(route('organizations.store'), ['name' => 'ACME bv', 'type' => 'customer'])
            ->assertSessionHasErrors('name');
        $this->actingAs($user)->post(route('organizations.store'), ['name' => 'Other', 'type' => 'customer', 'coc_number' => '123'])
            ->assertSessionHasErrors('coc_number');
    }

    public function test_company_can_be_deactivated_and_filtered(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Zeta Corp']);

        $this->actingAs($user)->patch(route('organizations.toggle-active', $organization));

        $this->assertFalse($organization->fresh()->is_active);
        $this->actingAs($user)->get(route('organizations.index'))->assertDontSee('Zeta Corp');
        $this->actingAs($user)->get(route('organizations.index', ['status' => 'inactive']))->assertSee('Zeta Corp');
    }

    public function test_person_duplicate_by_email_and_name_is_blocked(): void
    {
        $user = User::factory()->create();
        Person::factory()->create(['first_name' => 'Jan', 'last_name' => 'Berg', 'last_name_prefix' => 'van der', 'email' => 'jan@x.test']);

        $this->actingAs($user)->post(route('people.store'), ['last_name' => 'Smit', 'email' => 'jan@x.test'])
            ->assertSessionHasErrors('email');
        $this->actingAs($user)->post(route('people.store'), ['first_name' => 'Jan', 'last_name_prefix' => 'Van Der', 'last_name' => 'Berg'])
            ->assertSessionHasErrors('last_name');
        $this->actingAs($user)->post(route('people.store'), ['first_name' => 'Jan', 'last_name_prefix' => 'van der', 'last_name' => 'Berg', 'confirm_duplicate' => 1])
            ->assertSessionHasNoErrors();
    }

    public function test_person_links_to_multiple_companies_with_job_title_per_company(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->create();
        [$a, $b] = Organization::factory(2)->create();

        $this->actingAs($user)->post(route('links.store'), ['organization_id' => $a->id, 'person_id' => $person->id, 'job_title' => 'CEO']);
        $this->actingAs($user)->post(route('links.store'), ['organization_id' => $b->id, 'person_id' => $person->id, 'job_title' => 'Advisor']);
        $this->actingAs($user)->post(route('links.store'), ['organization_id' => $b->id, 'person_id' => $person->id])->assertSessionHasErrors('organization_id');

        $titles = $person->organizations()->get()->pluck('pivot.job_title', 'id');
        $this->assertSame('CEO', $titles[$a->id]);
        $this->assertSame('Advisor', $titles[$b->id]);

        $this->actingAs($user)->delete(route('links.destroy', [$a, $person]));
        $this->assertCount(1, $person->organizations()->get());
    }

    public function test_notes_can_be_added_to_company_and_person(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $person = Person::factory()->create();

        $this->actingAs($user)->post(route('organizations.notes.store', $organization), ['body' => 'Called them']);
        $this->actingAs($user)->post(route('people.notes.store', $person), ['body' => 'Prefers e-mail']);

        $this->assertSame('Called them', $organization->notes()->first()->body);
        $this->assertSame('Prefers e-mail', $person->notes()->first()->body);
    }

    public function test_company_import_skips_duplicates_and_export_round_trips(): void
    {
        $user = User::factory()->create();
        Organization::factory()->create(['name' => 'Existing', 'coc_number' => null, 'vat_number' => null]);

        $csv = "name;type;industry;email\nNew Co;customer;Software;a@b.test\nexisting bv;supplier;;\n;customer;;\nBad Type;alien;;\n";
        $file = UploadedFile::fake()->createWithContent('c.csv', $csv);

        $this->actingAs($user)->post(route('organizations.import.store'), ['file' => $file])
            ->assertSessionHas('import_result', fn ($r) => $r['created'] === 1 && count($r['skipped']) === 1 && count($r['errors']) === 2);

        $this->assertDatabaseHas('organizations', ['name' => 'New Co']);
        $this->assertDatabaseHas('industries', ['name' => 'Software']);

        $export = $this->actingAs($user)->get(route('organizations.export', ['format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('New Co', $export);
        $this->assertStringContainsString('name;type;industry', $export);
    }

    public function test_templates_have_matching_column_counts(): void
    {
        $user = User::factory()->create();

        foreach (['organizations', 'people'] as $resource) {
            $lines = explode("\n", trim($this->actingAs($user)->get(route("{$resource}.import.template"))->streamedContent()));
            $this->assertSame(substr_count($lines[0], ';'), substr_count($lines[1], ';'), $resource);
        }
    }

    public function test_people_import_links_companies_and_never_duplicates(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Acme']);

        $csv = "first_name;last_name;email;company;job_title\nJan;Berg;jan@x.test;Acme;CTO\nJan;Berg;jan@x.test;Acme;CTO\nPiet;Pot;;Nowhere;\n";
        $this->actingAs($user)->post(route('people.import.store'), ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)]);

        $this->assertSame(2, Person::count());
        $jan = Person::where('email', 'jan@x.test')->first();
        $this->assertSame('CTO', $jan->organizations()->first()->pivot->job_title);
        $this->assertTrue($organization->people()->whereKey($jan->id)->exists());
    }

    public function test_all_pages_render(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $person = Person::factory()->create();
        $person->organizations()->attach($organization->id, ['job_title' => 'CEO', 'is_primary' => true]);
        $organization->notes()->create(['body' => 'Hello', 'user_id' => $user->id]);

        $this->actingAs($user);

        foreach ([
            route('organizations.index', ['q' => 'a', 'type' => 'customer']), route('organizations.create'),
            route('organizations.show', $organization), route('organizations.edit', $organization), route('organizations.import'),
            route('people.index', ['q' => $person->last_name]), route('people.create'), route('people.show', $person),
            route('people.edit', $person), route('people.import'),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get(route('people.index', ['q' => $person->last_name]))->assertSee($person->full_name);
    }

    public function test_companies_can_be_filtered_on_multiple_types_and_invoice_delivery(): void
    {
        $user = User::factory()->create();
        Organization::factory()->create(['name' => 'Klant Een', 'type' => 'customer', 'invoice_delivery' => 'email']);
        Organization::factory()->create(['name' => 'Lead Twee', 'type' => 'lead', 'invoice_delivery' => 'pdf']);
        Organization::factory()->create(['name' => 'Partner Drie', 'type' => 'partner']);

        $this->actingAs($user)->get(route('organizations.index', ['type' => ['customer', 'lead']]))
            ->assertSee('Klant Een')->assertSee('Lead Twee')->assertDontSee('Partner Drie');
        $this->actingAs($user)->get(route('organizations.index', ['invoice_delivery' => ['pdf']]))
            ->assertSee('Lead Twee')->assertDontSee('Klant Een');
    }

    public function test_company_form_saves_note_and_only_keeps_postal_address_when_different(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'Nieuw BV', 'type' => 'customer', 'note' => 'Eerste contact via beurs',
            'postal_street' => 'Postbus 1', 'postal_city' => 'Utrecht',
        ])->assertSessionHasNoErrors();

        $organization = Organization::where('name', 'Nieuw BV')->firstOrFail();
        $this->assertNull($organization->postal_street);
        $this->assertSame('Eerste contact via beurs', $organization->notes()->first()->body);

        $this->actingAs($user)->put(route('organizations.update', $organization), [
            'name' => 'Nieuw BV', 'type' => 'customer', 'postal_differs' => 1, 'postal_street' => 'Postbus 1', 'postal_city' => 'Utrecht',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Postbus 1', $organization->fresh()->postal_street);
    }

    /**
     * @return array<int, string> Sheet names of a downloaded workbook.
     */
    private function sheetNames(BinaryFileResponse $response): array
    {
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        preg_match_all('/<sheet name="([^"]+)"/', $zip->getFromName('xl/workbook.xml'), $matches);
        $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet1.xml'));

        return array_map('html_entity_decode', $matches[1]);
    }

    public function test_exports_are_excel_workbooks_with_a_tab_per_subject(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Acme & Zn']);
        $person = Person::factory()->create();
        $person->organizations()->attach($organization->id, ['job_title' => 'CEO', 'is_primary' => true]);
        $organization->notes()->create(['body' => 'Eerste gesprek', 'user_id' => $user->id]);

        $companies = $this->actingAs($user)->get(route('organizations.export'))->assertOk();
        $this->assertSame(['Bedrijven', 'Facturatie', 'Personen', 'Notities'], $this->sheetNames($companies->baseResponse));

        $zip = new \ZipArchive;
        $zip->open($companies->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('Acme &amp; Zn', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('CEO', $zip->getFromName('xl/worksheets/sheet3.xml'));
        $this->assertStringContainsString('Eerste gesprek', $zip->getFromName('xl/worksheets/sheet4.xml'));

        $people = $this->actingAs($user)->get(route('people.export'))->assertOk();
        $this->assertSame(['Personen', 'Bedrijven', 'Notities'], $this->sheetNames($people->baseResponse));
    }
}
