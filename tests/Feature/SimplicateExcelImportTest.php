<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\ImportNormalizer;
use App\Services\XlsxReader;
use App\Services\XlsxWriter;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SimplicateExcelImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['user_group_id' => UserGroup::where('name', 'Beheerder')->value('id')]);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    /**
     * Build an .xlsx upload the way a Simplicate export looks: Dutch headings and values.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function workbook(array $headers, array $rows, string $name = 'export.xlsx'): UploadedFile
    {
        $file = XlsxWriter::make()->sheet('Export', $headers, $rows)->download($name)->getFile();

        return UploadedFile::fake()->createWithContent($name, (string) file_get_contents($file->getPathname()));
    }

    public function test_the_reader_returns_text_numbers_and_real_dates(): void
    {
        $upload = $this->workbook(['Naam', 'Aantal', 'Datum'], [['Eén & Co', 12.5, new DateTimeImmutable('2024-03-01')]]);

        $rows = (new XlsxReader)->read($upload->getRealPath());

        $this->assertSame(['Naam', 'Aantal', 'Datum'], $rows[1]);
        $this->assertSame(['Eén & Co', '12.5', '2024-03-01'], $rows[2]);
    }

    public function test_a_file_that_is_not_an_excel_workbook_is_rejected(): void
    {
        $upload = UploadedFile::fake()->createWithContent('kapot.xlsx', 'dit is geen zip');

        $this->actingAs($this->admin())->post(route('organizations.import.store'), ['file' => $upload])->assertSessionHasErrors('file');
        $this->assertSame(0, Organization::count());
    }

    public function test_headings_and_values_are_translated(): void
    {
        $normalizer = new ImportNormalizer;
        $organizationFields = ['name', 'visit_street', 'visit_zipcode', 'coc_number'];
        $personFields = ['first_name', 'street', 'zipcode'];

        $this->assertSame('name', $normalizer->field('Naam bedrijf', $organizationFields));
        $this->assertSame('coc_number', $normalizer->field('Nummer KvK', $organizationFields));
        $this->assertSame('visit_zipcode', $normalizer->field('Postcode', $organizationFields));
        $this->assertSame('zipcode', $normalizer->field('Postcode', $personFields));
        $this->assertSame('first_name', $normalizer->field('  VOORNAAM ', $personFields));
        $this->assertSame('onbekend', $normalizer->field('Onbekend', $personFields));

        $this->assertSame('customer', $normalizer->value('type', 'Klant'));
        $this->assertSame('30', $normalizer->value('payment_term_days', '30 dagen'));
        $this->assertSame('0', $normalizer->value('payment_term_days', 'Direct'));
        $this->assertSame('female', $normalizer->value('gender', 'Vrouw'));
        $this->assertSame('2024-03-01', $normalizer->value('hired_at', '01-03-2024'));
        $this->assertSame('1234.50', $normalizer->value('price', '€ 1.234,50'));
        $this->assertSame('125', $normalizer->value('hourly_rate', '125'));
        $this->assertSame('fixed_price', $normalizer->value('invoice_method', 'Vaste prijs'));
        $this->assertSame('after_calculation', $normalizer->value('invoice_method', 'Nacalculatie'));
        $this->assertSame('indefinite', $normalizer->value('contract_type', 'Onbepaalde tijd'));
        $this->assertSame('vat_21', $normalizer->value('default_vat_code', 'BTW hoog (21%)'));
        $this->assertSame('email', $normalizer->value('invoice_delivery', 'E-mail'));
    }

    public function test_organizations_import_from_a_simplicate_workbook(): void
    {
        $upload = $this->workbook(
            ['Naam bedrijf', 'Relatiesoort', 'Branche', 'Bedrijfsgrootte', 'E-mail', 'Telefoon', 'Nummer KvK', 'Adres', 'Postcode', 'Plaatsnaam', 'Land', 'Betaaltermijn', 'Factuurverstrekking', 'Relatiebeheerder', 'Actief'],
            [['Acme B.V.', 'Klant', 'Bouwnijverheid', '11-20 medewerkers', 'info@acme.nl', '020 1234567', '12345678', 'Keizersgracht 1', '1015 AA', 'Amsterdam', 'Nederland', '30 dagen', 'E-mail', 'Nexoh Micro', 'Ja']],
        );
        $admin = User::factory()->create(['name' => 'Nexoh Micro']);

        $this->actingAs($this->admin())->post(route('organizations.import.store'), ['file' => $upload])->assertRedirect();

        $organization = Organization::where('name', 'Acme B.V.')->firstOrFail();
        $this->assertSame('customer', $organization->type->value);
        $this->assertSame(30, (int) $organization->payment_term_days);
        $this->assertSame('email', $organization->invoice_delivery->value);
        $this->assertSame('Keizersgracht 1', $organization->visit_street);
        $this->assertSame('Amsterdam', $organization->visit_city);
        $this->assertSame('Bouwnijverheid', $organization->industry->name);
        $this->assertSame($admin->id, $organization->relation_manager_id);
        $this->assertTrue($organization->is_active);
    }

    public function test_people_import_with_dutch_gender_dates_and_company_link(): void
    {
        $organization = Organization::factory()->create(['name' => 'Acme B.V.']);
        $upload = $this->workbook(
            ['Voornaam', 'Tussenvoegsel', 'Achternaam', 'Geslacht', 'Geboortedatum', 'E-mail', 'Mobiel', 'Bedrijf', 'Functie'],
            [['Anne', 'de', 'Architect', 'Vrouw', new DateTimeImmutable('1990-05-17'), 'anne@acme.nl', '06 12345678', 'Acme B.V.', 'Directeur']],
        );

        $this->actingAs($this->admin())->post(route('people.import.store'), ['file' => $upload])->assertRedirect();

        $person = Person::where('last_name', 'Architect')->firstOrFail();
        $this->assertSame('female', $person->gender);
        $this->assertSame('1990-05-17', $person->date_of_birth->toDateString());
        $this->assertSame('de', $person->last_name_prefix);
        $this->assertSame('Directeur', $person->organizations()->firstOrFail()->pivot->job_title);
    }

    public function test_projects_import_with_dutch_headings_and_amounts(): void
    {
        Organization::factory()->create(['name' => 'Gemeente Amsterdam']);
        $manager = Employee::factory()->create(['first_name' => 'Herman', 'last_name' => 'Hoogbouw']);
        $upload = $this->workbook(
            ['Projectnummer', 'Projectnaam', 'Bedrijf', 'Projectleider', 'Startdatum', 'Dienst', 'Factuurmethode', 'Prijs'],
            [['2001', 'Nieuwbouw IJburg', 'Gemeente Amsterdam', 'Herman Hoogbouw', '19-10-2026', 'Schetsontwerp', 'Vaste prijs', '45.050,00']],
        );

        $this->actingAs($this->admin())->post(route('projects.import.store'), ['file' => $upload])->assertRedirect();

        $project = Project::where('name', 'Nieuwbouw IJburg')->firstOrFail();
        $this->assertSame($manager->id, $project->manager_id);
        $this->assertSame('2026-10-19', $project->start_date->toDateString());
        $service = $project->services()->firstOrFail();
        $this->assertSame('fixed_price', $service->invoice_method->value);
        $this->assertSame(45050.0, $service->price);
    }

    public function test_our_own_english_csv_still_imports(): void
    {
        $csv = "name;type;email\nPlain B.V.;customer;info@plain.nl\n";
        $upload = UploadedFile::fake()->createWithContent('bedrijven.csv', $csv);

        $this->actingAs($this->admin())->post(route('organizations.import.store'), ['file' => $upload])->assertRedirect();

        $this->assertSame('customer', Organization::where('name', 'Plain B.V.')->firstOrFail()->type->value);
    }
}
