<?php

namespace App\Http\Controllers;

use App\Enums\BillingCycle;
use App\Enums\InvoiceMethod;
use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\CostType;
use App\Models\DefaultService;
use App\Models\HourType;
use App\Models\Organization;
use App\Models\RevenueGroup;
use App\Services\XlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The service catalogue: templates that project services are created from.
 */
class DefaultServiceController extends Controller
{
    use HandlesCsv;

    /**
     * @var list<string>
     */
    private const FIELDS = [
        'name', 'description', 'internal_note', 'invoice_method', 'price', 'price_editable', 'billing_cycle', 'vat_code', 'revenue_group',
        'allow_hours', 'allow_costs', 'is_plannable', 'is_blocked', 'hour_type', 'hours', 'hourly_rate', 'cost_type', 'cost_quantity', 'purchase_price', 'margin_percentage',
    ];

    public function index(Request $request): View
    {
        Gate::authorize('projects.view');

        $services = DefaultService::query()
            ->with(['revenueGroup', 'hourLines', 'costLines'])
            ->withCount('projectServices')
            ->filter($request->query())
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('services.index', ['services' => $services, 'status' => $request->query('status', 'available'), 'counts' => ['available' => DefaultService::available()->count(), 'blocked' => DefaultService::where('is_blocked', true)->count()]]);
    }

    public function create(): View
    {
        Gate::authorize('projects.edit');

        return view('services.form', ['service' => new DefaultService(['invoice_method' => InvoiceMethod::AfterCalculation, 'allow_hours' => true, 'allow_costs' => true, 'is_plannable' => true, 'price_editable' => true]), ...$this->lookups()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $service = DB::transaction(function () use ($request): DefaultService {
            $service = DefaultService::create($this->validated($request));
            $this->syncLines($service, $request);

            return $service;
        });

        return redirect()->route('services.edit', $service)->with('status', 'Dienst aangemaakt.');
    }

    public function edit(DefaultService $service): View
    {
        Gate::authorize('projects.view');

        return view('services.form', ['service' => $service->load(['hourLines', 'costLines'])->loadCount('projectServices'), ...$this->lookups()]);
    }

    public function update(Request $request, DefaultService $service): RedirectResponse
    {
        Gate::authorize('projects.edit');

        DB::transaction(function () use ($request, $service): void {
            $service->update($this->validated($request, $service));
            $this->syncLines($service, $request);
        });

        return redirect()->route('services.edit', $service)->with('status', 'Dienst opgeslagen.');
    }

    public function toggleBlocked(DefaultService $service): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $service->update(['is_blocked' => ! $service->is_blocked]);

        return back()->with('status', $service->is_blocked ? 'Dienst geblokkeerd. Bestaande projecten behouden hem.' : 'Dienst weer beschikbaar.');
    }

    public function destroy(DefaultService $service): RedirectResponse
    {
        Gate::authorize('projects.edit');

        if ($service->projectServices()->exists()) {
            return back()->withErrors(['service' => 'Deze dienst wordt gebruikt in projecten. Blokkeer hem in plaats van te verwijderen, dan blijft de historie bewaard.']);
        }

        $service->delete();

        return redirect()->route('services.index')->with('status', 'Dienst verwijderd.');
    }

    public function export(Request $request): StreamedResponse|BinaryFileResponse
    {
        Gate::authorize('projects.view');

        $services = DefaultService::with(['revenueGroup', 'hourLines.hourType', 'costLines.costType'])->withCount('projectServices')->filter($request->query())->orderBy('name')->get();

        if ($request->query('format') === 'csv') {
            return $this->streamCsv('diensten-'.now()->format('Y-m-d').'.csv', self::FIELDS, $this->flatRows($services));
        }

        $yes = fn (bool $value) => $value ? 'Ja' : 'Nee';

        return XlsxWriter::make()
            ->sheet('Diensten', ['Dienst', 'Beschrijving', 'Interne toelichting', 'Factuurmethode', 'Prijs', 'Prijs aanpasbaar', 'Facturatieperiode', 'Btw-code', 'Omzetgroep', 'Uren boeken', 'Kosten boeken', 'Planbaar', 'Geblokkeerd', 'In projecten'],
                $services->map(fn (DefaultService $s) => [$s->name, $s->description, $s->internal_note, $s->invoice_method?->label(), $s->price, $yes($s->price_editable), $s->billing_cycle?->label(), Organization::VAT_CODES[$s->vat_code] ?? null, $s->revenueGroup?->name, $yes($s->allow_hours), $yes($s->allow_costs), $yes($s->is_plannable), $yes($s->is_blocked), $s->project_services_count]))
            ->sheet('Urensoorten', ['Dienst', 'Urensoort', 'Uren', 'Uurtarief'], $services->flatMap(fn (DefaultService $s) => $s->hourLines->map(fn ($l) => [$s->name, $l->hourType->name, $l->hours, $l->rate])))
            ->sheet('Kostensoorten', ['Dienst', 'Kostensoort', 'Aantal', 'Inkoopprijs', 'Marge %'], $services->flatMap(fn (DefaultService $s) => $s->costLines->map(fn ($l) => [$s->name, $l->costType->name, $l->quantity, $l->purchase_price, $l->margin_percentage])))
            ->download('diensten-'.now()->format('Y-m-d').'.xlsx');
    }

    public function template(): StreamedResponse
    {
        Gate::authorize('projects.edit');

        $base = ['name' => 'Schetsontwerp', 'description' => 'Eerste schetsen met de opdrachtgever', 'internal_note' => 'Alleen intern', 'invoice_method' => 'fixed_price', 'price' => '45050', 'price_editable' => 'yes',
            'vat_code' => 'vat_21', 'revenue_group' => 'Algemeen', 'allow_hours' => 'yes', 'allow_costs' => 'yes', 'is_plannable' => 'yes', 'is_blocked' => 'no'];

        return $this->streamCsv('diensten-sjabloon.csv', self::FIELDS, [
            array_map(fn ($f) => ($base + ['hour_type' => 'Architect', 'hours' => '120', 'hourly_rate' => '125'])[$f] ?? '', self::FIELDS),
            array_map(fn ($f) => ($base + ['hour_type' => 'Tekenaar', 'hours' => '80', 'hourly_rate' => '95'])[$f] ?? '', self::FIELDS),
            array_map(fn ($f) => ($base + ['cost_type' => 'Inhuur derden', 'cost_quantity' => '1', 'purchase_price' => '2000', 'margin_percentage' => '10'])[$f] ?? '', self::FIELDS),
        ]);
    }

    public function importForm(): View
    {
        Gate::authorize('projects.edit');

        return view('services.import', ['fields' => self::FIELDS, 'methods' => array_keys(InvoiceMethod::options()), 'cycles' => array_keys(BillingCycle::options()), 'vatCodes' => array_keys(Organization::VAT_CODES)]);
    }

    /**
     * One row per hour or cost line; rows with the same name form one service. An existing service is updated and gets the lines of the file.
     */
    public function import(Request $request): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $rows = $this->readCsv($request->file('file'));

        if ($rows === [] || ! array_key_exists('name', reset($rows))) {
            return back()->withErrors(['file' => 'Het bestand is leeg of bevat geen kolom "name". Download het sjabloon voor het juiste formaat.']);
        }

        $result = ['created' => 0, 'updated' => 0, 'errors' => []];
        $seen = [];

        DB::transaction(function () use ($rows, &$result, &$seen): void {
            foreach ($rows as $line => $row) {
                $validator = Validator::make($row, [
                    'name' => ['required', 'string', 'max:100'],
                    'invoice_method' => ['nullable', Rule::enum(InvoiceMethod::class)],
                    'billing_cycle' => ['nullable', Rule::enum(BillingCycle::class)],
                    'vat_code' => ['nullable', Rule::in(array_keys(Organization::VAT_CODES))],
                    'price' => ['nullable', 'numeric', 'min:0'],
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

                $key = mb_strtolower($row['name']);

                if (! isset($seen[$key])) {
                    $service = DefaultService::firstWhere('name', $row['name']) ?? new DefaultService(['name' => $row['name']]);
                    $isNew = ! $service->exists;
                    $service->fill($this->importAttributes($row, $service));
                    $service->save();

                    if (! $isNew) {
                        $service->hourLines()->delete();
                        $service->costLines()->delete();
                    }

                    $result[$isNew ? 'created' : 'updated']++;
                    $seen[$key] = $service;
                }

                $this->addImportLines($seen[$key], $row);
            }
        });

        return redirect()->route('services.import')->with('import_result', $result);
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function importAttributes(array $row, DefaultService $service): array
    {
        $method = $this->nullIfBlank($row['invoice_method'] ?? null);
        $group = ($name = $this->nullIfBlank($row['revenue_group'] ?? null)) ? RevenueGroup::firstOrCreate(['name' => $name])->id : null;

        return [
            'description' => $this->nullIfBlank($row['description'] ?? null),
            'internal_note' => $this->nullIfBlank($row['internal_note'] ?? null),
            'invoice_method' => $method,
            'price' => in_array($method, ['fixed_price', 'subscription'], true) ? $this->nullIfBlank($row['price'] ?? null) : null,
            'price_editable' => $this->parseBoolean($row['price_editable'] ?? null),
            'billing_cycle' => $method === 'subscription' ? ($this->nullIfBlank($row['billing_cycle'] ?? null) ?? BillingCycle::Monthly->value) : null,
            'vat_code' => $this->nullIfBlank($row['vat_code'] ?? null),
            'revenue_group_id' => $group,
            'allow_hours' => $this->parseBoolean($row['allow_hours'] ?? null),
            'allow_costs' => $this->parseBoolean($row['allow_costs'] ?? null),
            'is_plannable' => $this->parseBoolean($row['is_plannable'] ?? null),
            'is_blocked' => $this->parseBoolean($row['is_blocked'] ?? null, false),
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    private function addImportLines(DefaultService $service, array $row): void
    {
        if ($name = $this->nullIfBlank($row['hour_type'] ?? null)) {
            $type = HourType::firstOrCreate(['name' => $name], ['default_rate' => (float) ($row['hourly_rate'] ?? 0)]);

            $service->hourLines()->create(['hour_type_id' => $type->id, 'hours' => (float) ($row['hours'] ?? 0), 'rate' => $this->nullIfBlank($row['hourly_rate'] ?? null) ?? $type->default_rate]);
        }

        if ($name = $this->nullIfBlank($row['cost_type'] ?? null)) {
            $type = CostType::firstOrCreate(['name' => $name]);

            $service->costLines()->create([
                'cost_type_id' => $type->id,
                'quantity' => $this->nullIfBlank($row['cost_quantity'] ?? null) ?? 1,
                'purchase_price' => $this->nullIfBlank($row['purchase_price'] ?? null) ?? 0,
                'margin_percentage' => $this->nullIfBlank($row['margin_percentage'] ?? null) ?? $type->default_margin,
            ]);
        }
    }

    /**
     * @param  iterable<DefaultService>  $services
     * @return list<list<mixed>>
     */
    private function flatRows(iterable $services): array
    {
        $yes = fn (bool $value) => $value ? 'yes' : 'no';
        $rows = [];

        foreach ($services as $s) {
            $base = [$s->name, $s->description, $s->internal_note, $s->invoice_method?->value, $s->price, $yes($s->price_editable), $s->billing_cycle?->value, $s->vat_code, $s->revenueGroup?->name, $yes($s->allow_hours), $yes($s->allow_costs), $yes($s->is_plannable), $yes($s->is_blocked)];
            $lines = [];

            foreach ($s->hourLines as $l) {
                $lines[] = [$l->hourType->name, $l->hours, $l->rate, '', '', '', ''];
            }

            foreach ($s->costLines as $l) {
                $lines[] = ['', '', '', $l->costType->name, $l->quantity, $l->purchase_price, $l->margin_percentage];
            }

            foreach ($lines ?: [['', '', '', '', '', '', '']] as $line) {
                $rows[] = [...$base, ...$line];
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?DefaultService $service = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('default_services', 'name')->ignore($service)],
            'description' => ['nullable', 'string', 'max:5000'],
            'internal_note' => ['nullable', 'string', 'max:5000'],
            'invoice_method' => ['required', Rule::enum(InvoiceMethod::class)],
            'billing_cycle' => ['nullable', Rule::enum(BillingCycle::class)],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'vat_code' => ['nullable', Rule::in(array_keys(Organization::VAT_CODES))],
            'revenue_group_id' => ['nullable', 'exists:revenue_groups,id'],
            'hour_lines' => ['nullable', 'array'],
            'hour_lines.*.hour_type_id' => ['nullable', 'exists:hour_types,id'],
            'hour_lines.*.hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'hour_lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'cost_lines' => ['nullable', 'array'],
            'cost_lines.*.cost_type_id' => ['nullable', 'exists:cost_types,id'],
            'cost_lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'cost_lines.*.purchase_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'cost_lines.*.margin_percentage' => ['nullable', 'numeric', 'between:0,1000'],
        ], [], ['name' => 'naam', 'invoice_method' => 'factuurmethode', 'price' => 'prijs', 'billing_cycle' => 'facturatieperiode']);

        $paid = in_array($data['invoice_method'], ['fixed_price', 'subscription'], true);

        foreach (['price_editable', 'allow_hours', 'allow_costs', 'is_plannable'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $data['price'] = $paid ? ($data['price'] ?? null) : null;
        $data['billing_cycle'] = $data['invoice_method'] === 'subscription' ? ($data['billing_cycle'] ?? BillingCycle::Monthly->value) : null;

        return collect($data)->except(['hour_lines', 'cost_lines'])->all();
    }

    private function syncLines(DefaultService $service, Request $request): void
    {
        $service->hourLines()->delete();
        $service->costLines()->delete();

        foreach ((array) $request->input('hour_lines', []) as $line) {
            if (empty($line['hour_type_id'])) {
                continue;
            }

            $type = HourType::find($line['hour_type_id']);
            $service->hourLines()->create(['hour_type_id' => $type->id, 'hours' => ($line['hours'] ?? '') !== '' ? $line['hours'] : 0, 'rate' => ($line['rate'] ?? '') !== '' ? $line['rate'] : $type->default_rate]);
        }

        foreach ((array) $request->input('cost_lines', []) as $line) {
            if (empty($line['cost_type_id']) || ($line['purchase_price'] ?? '') === '') {
                continue;
            }

            $type = CostType::find($line['cost_type_id']);
            $service->costLines()->create([
                'cost_type_id' => $type->id,
                'quantity' => ($line['quantity'] ?? '') !== '' ? $line['quantity'] : 1,
                'purchase_price' => $line['purchase_price'],
                'margin_percentage' => ($line['margin_percentage'] ?? '') !== '' ? $line['margin_percentage'] : $type->default_margin,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function lookups(): array
    {
        return [
            'revenueGroups' => RevenueGroup::orderBy('name')->pluck('name', 'id'),
            'hourTypes' => HourType::orderBy('name')->get(),
            'costTypes' => CostType::orderBy('name')->get(),
            'methods' => InvoiceMethod::options(),
            'cycles' => BillingCycle::options(),
        ];
    }
}
