<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\Organization;
use App\Models\Person;
use App\Services\XlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonImportExportController extends Controller
{
    use HandlesCsv;

    private const PERSON_FIELDS = [
        'first_name', 'initials', 'last_name_prefix', 'last_name', 'gender', 'date_of_birth', 'email', 'phone', 'mobile',
        'linkedin_url', 'street', 'zipcode', 'city', 'country',
    ];

    private const FIELDS = [...self::PERSON_FIELDS, 'is_active', 'company', 'job_title', 'department'];

    public function form(): View
    {
        return view('people.import', ['fields' => self::FIELDS]);
    }

    /**
     * One row per person/company link, so people with several companies repeat.
     */
    public function export(Request $request): StreamedResponse|BinaryFileResponse
    {
        if ($request->query('format') !== 'csv') {
            return $this->exportWorkbook($request);
        }

        $people = Person::query()
            ->with('organizations')
            ->filter($request->query())
            ->orderBy('last_name')->orderBy('first_name')
            ->cursor();

        $rows = (function () use ($people) {
            foreach ($people as $person) {
                $base = $person->only(self::PERSON_FIELDS);
                $base['date_of_birth'] = $person->date_of_birth?->format('Y-m-d');
                $links = $person->organizations->isEmpty() ? [null] : $person->organizations;

                foreach ($links as $organization) {
                    $row = [...$base, 'is_active' => $person->is_active ? 'yes' : 'no',
                        'company' => $organization?->name,
                        'job_title' => $organization?->pivot->job_title,
                        'department' => $organization?->pivot->department,
                    ];

                    yield array_map(fn ($field) => $row[$field] ?? null, self::FIELDS);
                }
            }
        })();

        return $this->streamCsv('personen-'.now()->format('Y-m-d').'.csv', self::FIELDS, $rows);
    }

    private function exportWorkbook(Request $request): BinaryFileResponse
    {
        $people = Person::query()->with(['organizations', 'notes.author'])->filter($request->query())
            ->orderBy('last_name')->orderBy('first_name')->get();
        $yes = fn (bool $value) => $value ? 'Ja' : 'Nee';
        $gender = ['male' => 'Man', 'female' => 'Vrouw', 'other' => 'Anders'];

        $rows = $people->map(fn (Person $p) => [
            $p->full_name, $p->initials, $gender[$p->gender] ?? null, $p->date_of_birth, $p->email, $p->phone, $p->mobile, $p->linkedin_url,
            $p->street, $p->zipcode, $p->city, $p->country, $yes($p->is_active), $p->created_at,
        ]);

        $links = $people->flatMap(fn (Person $p) => $p->organizations->map(fn ($o) => [
            $p->full_name, $o->name, $o->pivot->job_title, $o->pivot->department, $yes((bool) $o->pivot->is_primary),
        ]));

        $notes = $people->flatMap(fn (Person $p) => $p->notes->map(fn ($n) => [$p->full_name, $n->created_at, $n->author?->name, $n->body]));

        return XlsxWriter::make()
            ->sheet('Personen', ['Naam', 'Voorletters', 'Geslacht', 'Geboortedatum', 'E-mail', 'Telefoon', 'Mobiel', 'LinkedIn', 'Adres', 'Postcode', 'Plaats', 'Land', 'Actief', 'Aangemaakt op'], $rows)
            ->sheet('Bedrijven', ['Persoon', 'Bedrijf', 'Functie', 'Afdeling', 'Hoofdbedrijf'], $links)
            ->sheet('Notities', ['Persoon', 'Datum', 'Auteur', 'Notitie'], $notes)
            ->download('personen-'.now()->format('Y-m-d').'.xlsx');
    }

    public function template(): StreamedResponse
    {
        return $this->streamCsv('personen-sjabloon.csv', self::FIELDS, [[
            'Jan', 'J.', 'van der', 'Berg', 'male', '1985-04-12', 'jan@acme.test', '+31 20 123 4567', '+31 6 12345678',
            '', 'Keizersgracht 1', '1015 AA', 'Amsterdam', 'Netherlands', 'yes', 'Acme B.V.', 'Sales Manager', 'Sales',
        ]]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $rows = $this->readCsv($request->file('file'));

        if ($rows === [] || ! array_key_exists('last_name', reset($rows))) {
            return back()->withErrors(['file' => 'Het bestand is leeg of bevat geen kolom "last_name". Download het sjabloon voor het juiste formaat.']);
        }

        $result = ['created' => 0, 'linked' => 0, 'skipped' => [], 'errors' => []];

        foreach ($rows as $line => $row) {
            $data = [];
            foreach (self::PERSON_FIELDS as $field) {
                $data[$field] = $this->nullIfBlank($row[$field] ?? null);
            }
            $data['gender'] = $data['gender'] ? strtolower($data['gender']) : null;
            $data['is_active'] = $this->parseBoolean($row['is_active'] ?? null);

            $validator = Validator::make($data, [
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['nullable', 'email:rfc', 'max:255'],
                'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
                'date_of_birth' => ['nullable', 'date', 'before:today'],
            ]);

            if ($validator->fails()) {
                $result['errors'][] = "Regel {$line}: ".$validator->errors()->first();

                continue;
            }

            $person = $this->findExisting($data);
            $created = false;

            if (! $person) {
                $person = Person::create($data);
                $created = true;
                $result['created']++;
            }

            $companyName = $this->nullIfBlank($row['company'] ?? null);
            $organization = $companyName
                ? Organization::where('name_normalized', Organization::normalizeName($companyName))->first()
                : null;

            if ($companyName && ! $organization) {
                $result['errors'][] = "Regel {$line}: bedrijf \"{$companyName}\" niet gevonden; ".($created ? 'persoon aangemaakt zonder koppeling.' : 'koppeling overgeslagen.');
            }

            if ($organization && ! $person->organizations()->whereKey($organization->id)->exists()) {
                $person->organizations()->attach($organization->id, [
                    'job_title' => $this->nullIfBlank($row['job_title'] ?? null),
                    'department' => $this->nullIfBlank($row['department'] ?? null),
                    'is_primary' => $person->organizations()->doesntExist(),
                ]);
                $result['linked']++;
            } elseif (! $created) {
                $result['skipped'][] = "Regel {$line}: {$person->full_name} (bestaat al)";
            }
        }

        return redirect()->route('people.import')->with('import_result', $result);
    }

    /**
     * Match on e-mail first, otherwise on the normalised full name.
     *
     * @param  array<string, mixed>  $data
     */
    private function findExisting(array $data): ?Person
    {
        if ($data['email'] && ($person = Person::where('email', $data['email'])->first())) {
            return $person;
        }

        return Person::where('full_name_normalized', Person::normalizeFullName(
            $data['first_name'], $data['last_name_prefix'], $data['last_name'],
        ))->first();
    }
}
