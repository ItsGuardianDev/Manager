<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceMethod;
use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\CostType;
use App\Models\Employee;
use App\Models\HourType;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectService;
use App\Services\XlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectImportExportController extends Controller
{
    use HandlesCsv;

    /**
     * One row per service line: project columns repeat, a service repeats once per hour or cost line.
     *
     * @var list<string>
     */
    private const FIELDS = [
        'project_number', 'project_name', 'company', 'manager_email', 'start_date', 'end_date', 'is_invoiceable',
        'service_name', 'invoice_method', 'price', 'discount_percentage',
        'hour_type', 'hours', 'hourly_rate', 'cost_type', 'cost_quantity', 'purchase_price', 'margin_percentage',
    ];

    public function form(): View
    {
        Gate::authorize('projects.edit');

        return view('projects.import', ['fields' => self::FIELDS, 'methods' => array_keys(InvoiceMethod::options())]);
    }

    public function export(Request $request): StreamedResponse|BinaryFileResponse
    {
        Gate::authorize('projects.view');

        $projects = Project::query()
            ->with(['organization', 'manager', ...Project::BUDGET_RELATIONS, 'services.hourLines.hourType', 'services.costLines.costType', 'timeEntries.employee', 'timeEntries.service', 'purchases.supplier', 'purchases.lines.service'])
            ->inStatus($request->query('tab', 'all'))
            ->filter($request->query())
            ->orderBy('number')
            ->get();

        if ($request->query('format') === 'csv') {
            return $this->streamCsv('projecten-'.now()->format('Y-m-d').'.csv', self::FIELDS, $this->flatRows($projects));
        }

        $yes = fn (bool $value) => $value ? 'Ja' : 'Nee';

        $list = $projects->map(fn (Project $p) => [
            $p->number, $p->name, $p->organization?->name ?? 'Intern', $p->status->label(), $yes($p->is_on_hold), $p->manager?->full_name, $p->start_date, $p->end_date,
            $yes($p->is_invoiceable), $p->budgetHours(), $p->spentHours(), $p->totalBudget(), $p->totalSpent(), $p->totalRemaining(), $p->consumption() / 100, $p->progress() / 100,
        ]);

        $services = $projects->flatMap(fn (Project $p) => $p->services->map(fn (ProjectService $s) => [
            $p->number, $p->name, $s->name, $s->invoice_method->label(), $yes($s->is_active), $s->netPrice(), $s->discount_percentage, $s->completion_percentage,
            $s->budgetHours(), $s->spentHours(), $s->budgetHoursAmount(), $s->spentHoursAmount(), $s->budgetCosts(), $s->spentCosts(),
        ]));

        $hourBudget = $projects->flatMap(fn (Project $p) => $p->services->flatMap(fn (ProjectService $s) => $s->hourLines->map(fn ($line) => [
            $p->number, $p->name, $s->name, $line->hourType->name, $line->hours, $line->rate, $line->amount(),
        ])));

        $costBudget = $projects->flatMap(fn (Project $p) => $p->services->flatMap(fn (ProjectService $s) => $s->costLines->map(fn ($line) => [
            $p->number, $p->name, $s->name, $line->costType->name, $line->quantity, $line->purchase_price, $line->margin_percentage, $line->sellingTotal(),
        ])));

        $hours = $projects->flatMap(fn (Project $p) => $p->timeEntries->map(fn ($entry) => [
            $p->number, $p->name, $entry->date, $entry->employee->full_name, $entry->service?->name, $entry->hourType?->name, $entry->hours, $entry->rate, $entry->amount(), $entry->note,
        ]));

        $purchases = $projects->flatMap(fn (Project $p) => $p->purchases->flatMap(fn ($purchase) => $purchase->lines->map(fn ($line) => [
            $p->number, $p->name, $purchase->date, $purchase->description, $purchase->supplier?->name, $purchase->status->label(), $line->service?->name, $line->description, $line->quantity, $line->unit_price, $line->total(),
        ])));

        return XlsxWriter::make()
            ->sheet('Projecten', ['Nummer', 'Project', 'Klant', 'Status', 'In afwachting', 'Projectleider', 'Begindatum', 'Einddatum', 'Factureren', 'Budget uren', 'Besteed uren', 'Totaal budget', 'Besteed', 'Restant', 'Budget verbruikt', 'Voortgang'], $list)
            ->sheet('Diensten', ['Nummer', 'Project', 'Dienst', 'Factuurmethode', 'Actief', 'Prijs (netto)', 'Korting %', 'Voltooid %', 'Budget uren', 'Besteed uren', 'Budget uren (€)', 'Besteed uren (€)', 'Budget kosten (€)', 'Besteed kosten (€)'], $services)
            ->sheet('Urenbudget', ['Nummer', 'Project', 'Dienst', 'Urensoort', 'Uren', 'Uurtarief', 'Bedrag'], $hourBudget)
            ->sheet('Kostenbudget', ['Nummer', 'Project', 'Dienst', 'Kostensoort', 'Aantal', 'Inkoopprijs', 'Marge %', 'Verkoopwaarde'], $costBudget)
            ->sheet('Geregistreerde uren', ['Nummer', 'Project', 'Datum', 'Medewerker', 'Dienst', 'Urensoort', 'Uren', 'Tarief', 'Bedrag', 'Opmerking'], $hours)
            ->sheet('Inkoop', ['Nummer', 'Project', 'Datum', 'Omschrijving', 'Crediteur', 'Status', 'Dienst', 'Regel', 'Aantal', 'Prijs', 'Totaal'], $purchases)
            ->download('projecten-'.now()->format('Y-m-d').'.xlsx');
    }

    public function template(): StreamedResponse
    {
        Gate::authorize('projects.edit');

        $base = ['project_number' => '1001', 'project_name' => 'Nieuwbouw Ijburg', 'company' => 'Gemeente Amsterdam', 'manager_email' => 'admin@example.com',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_invoiceable' => 'yes', 'service_name' => 'Schetsontwerp', 'invoice_method' => 'fixed_price',
            'price' => '45050', 'discount_percentage' => '0'];

        return $this->streamCsv('projecten-sjabloon.csv', self::FIELDS, [
            array_map(fn ($f) => ($base + ['hour_type' => 'Architect', 'hours' => '120', 'hourly_rate' => '125'])[$f] ?? '', self::FIELDS),
            array_map(fn ($f) => ($base + ['hour_type' => 'Tekenaar', 'hours' => '80', 'hourly_rate' => '95'])[$f] ?? '', self::FIELDS),
            array_map(fn ($f) => ($base + ['cost_type' => 'Inhuur derden', 'cost_quantity' => '1', 'purchase_price' => '20000', 'margin_percentage' => '0'])[$f] ?? '', self::FIELDS),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $rows = $this->readCsv($request->file('file'));

        if ($rows === [] || ! array_key_exists('project_name', reset($rows))) {
            return back()->withErrors(['file' => 'Het bestand is leeg of bevat geen kolom "project_name". Download het sjabloon voor het juiste formaat.']);
        }

        $result = ['created' => 0, 'services' => 0, 'skipped' => [], 'errors' => []];
        $projects = [];
        $services = [];

        DB::transaction(function () use ($rows, &$result, &$projects, &$services, $request): void {
            foreach ($rows as $line => $row) {
                $validator = Validator::make($row, [
                    'project_name' => ['required', 'string', 'max:255'],
                    'start_date' => ['nullable', 'date'],
                    'end_date' => ['nullable', 'date'],
                    'invoice_method' => ['nullable', Rule::enum(InvoiceMethod::class)],
                    'price' => ['nullable', 'numeric', 'min:0'],
                    'discount_percentage' => ['nullable', 'numeric', 'between:0,100'],
                    'hours' => ['nullable', 'numeric', 'min:0'],
                    'hourly_rate' => ['nullable', 'numeric', 'min:0'],
                    'cost_quantity' => ['nullable', 'numeric', 'min:0'],
                    'purchase_price' => ['nullable', 'numeric', 'min:0'],
                    'margin_percentage' => ['nullable', 'numeric', 'between:0,1000'],
                ]);

                if ($validator->fails()) {
                    $result['errors'][] = "Regel {$line}: ".$validator->errors()->first();

                    continue;
                }

                $key = $this->nullIfBlank($row['project_number'] ?? null) ?? 'naam:'.mb_strtolower($row['project_name']);

                if (! isset($projects[$key])) {
                    $project = $this->findOrCreateProject($row, $line, $result, $request);

                    if (! $project) {
                        continue;
                    }

                    $projects[$key] = $project;
                }

                $project = $projects[$key];

                if ($serviceName = $this->nullIfBlank($row['service_name'] ?? null)) {
                    $serviceKey = $key.'|'.mb_strtolower($serviceName);

                    if (! isset($services[$serviceKey])) {
                        $services[$serviceKey] = $project->services()->create([
                            'name' => $serviceName,
                            'invoice_method' => $this->nullIfBlank($row['invoice_method'] ?? null) ?? InvoiceMethod::AfterCalculation->value,
                            'price' => $this->nullIfBlank($row['price'] ?? null),
                            'discount_percentage' => $this->nullIfBlank($row['discount_percentage'] ?? null) ?? 0,
                            'sort_order' => $project->services()->count() + 1,
                        ]);
                        $result['services']++;
                    }

                    $this->addLines($services[$serviceKey], $row, $line, $result);
                }
            }
        });

        return redirect()->route('projects.import')->with('import_result', $result);
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $result
     */
    private function findOrCreateProject(array $row, int $line, array &$result, Request $request): ?Project
    {
        $number = $this->nullIfBlank($row['project_number'] ?? null);
        $existing = $number ? Project::where('number', $number)->first() : Project::where('name', $row['project_name'])->first();

        if ($existing) {
            $result['skipped'][] = "Regel {$line}: project {$existing->name} bestaat al (diensten worden toegevoegd).";

            return $existing;
        }

        $invoiceable = $this->parseBoolean($row['is_invoiceable'] ?? null);
        $organization = null;

        if ($company = $this->nullIfBlank($row['company'] ?? null)) {
            $organization = Organization::where('name_normalized', Organization::normalizeName($company))->first();

            if (! $organization) {
                $result['errors'][] = "Regel {$line}: bedrijf \"{$company}\" niet gevonden.";

                return null;
            }
        } elseif ($invoiceable) {
            $result['errors'][] = "Regel {$line}: een te factureren project heeft een bedrijf nodig.";

            return null;
        }

        $manager = ($email = $this->nullIfBlank($row['manager_email'] ?? null)) ? Employee::where('email', strtolower($email))->first() : null;

        $project = Project::create([
            'number' => $number ?? Project::nextNumber(),
            'name' => $row['project_name'],
            'organization_id' => $organization?->id,
            'manager_id' => $manager?->id,
            'is_invoiceable' => $invoiceable,
            'start_date' => $this->nullIfBlank($row['start_date'] ?? null),
            'end_date' => $this->nullIfBlank($row['end_date'] ?? null),
        ]);
        $project->logEvent('Project geïmporteerd door '.$request->user()->name.'.');
        $result['created']++;

        return $project;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $result
     */
    private function addLines(ProjectService $service, array $row, int $line, array &$result): void
    {
        if ($name = $this->nullIfBlank($row['hour_type'] ?? null)) {
            $type = HourType::firstOrCreate(['name' => $name], ['default_rate' => (float) ($row['hourly_rate'] ?? 0)]);

            if ((float) ($row['hours'] ?? 0) > 0) {
                $service->hourLines()->create([
                    'hour_type_id' => $type->id,
                    'hours' => $row['hours'],
                    'rate' => $this->nullIfBlank($row['hourly_rate'] ?? null) ?? $type->default_rate,
                ]);
            }
        }

        if ($name = $this->nullIfBlank($row['cost_type'] ?? null)) {
            $type = CostType::firstOrCreate(['name' => $name]);

            if ($this->nullIfBlank($row['purchase_price'] ?? null) !== null) {
                $service->costLines()->create([
                    'cost_type_id' => $type->id,
                    'quantity' => $this->nullIfBlank($row['cost_quantity'] ?? null) ?? 1,
                    'purchase_price' => $row['purchase_price'],
                    'margin_percentage' => $this->nullIfBlank($row['margin_percentage'] ?? null) ?? $type->default_margin,
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return \Generator<int, array<int, mixed>>
     */
    private function flatRows($projects): \Generator
    {
        foreach ($projects as $project) {
            $base = [
                'project_number' => $project->number, 'project_name' => $project->name, 'company' => $project->organization?->name,
                'manager_email' => $project->manager?->email, 'start_date' => $project->start_date?->format('Y-m-d'), 'end_date' => $project->end_date?->format('Y-m-d'),
                'is_invoiceable' => $project->is_invoiceable ? 'yes' : 'no',
            ];

            if ($project->services->isEmpty()) {
                yield array_map(fn ($f) => $base[$f] ?? null, self::FIELDS);
            }

            foreach ($project->services as $service) {
                $serviceBase = $base + ['service_name' => $service->name, 'invoice_method' => $service->invoice_method->value, 'price' => $service->price, 'discount_percentage' => $service->discount_percentage];
                $lines = [];

                foreach ($service->hourLines as $line) {
                    $lines[] = $serviceBase + ['hour_type' => $line->hourType->name, 'hours' => $line->hours, 'hourly_rate' => $line->rate];
                }
                foreach ($service->costLines as $line) {
                    $lines[] = $serviceBase + ['cost_type' => $line->costType->name, 'cost_quantity' => $line->quantity, 'purchase_price' => $line->purchase_price, 'margin_percentage' => $line->margin_percentage];
                }

                foreach ($lines ?: [$serviceBase] as $values) {
                    yield array_map(fn ($f) => $values[$f] ?? null, self::FIELDS);
                }
            }
        }
    }
}
