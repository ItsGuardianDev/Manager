<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceMethod;
use App\Models\CostType;
use App\Models\DefaultService;
use App\Models\HourType;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\RevenueGroup;
use App\Services\BudgetMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectServiceController extends Controller
{
    public function create(Project $project): View
    {
        Gate::authorize('projects.edit');

        return view('projects.service-form', ['project' => $project, 'service' => new ProjectService(['invoice_method' => InvoiceMethod::FixedPrice, 'is_active' => true, 'discount_percentage' => 0, 'completion_percentage' => 0]), ...$this->lookups()]);
    }

    public function store(Request $request, Project $project, BudgetMonitor $monitor): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $service = DB::transaction(function () use ($request, $project): ProjectService {
            $service = $project->services()->create([...$this->validated($request), 'sort_order' => ($project->services()->max('sort_order') ?? 0) + 1]);
            $this->syncLines($service, $request);

            return $service;
        });

        $project->logEvent("Dienst \"{$service->name}\" toegevoegd door {$request->user()->name}.");
        $monitor->check($project);

        return redirect()->route('projects.show', [$project, 'tab' => 'services'])->with('status', 'Dienst toegevoegd.');
    }

    public function edit(Project $project, ProjectService $service): View
    {
        Gate::authorize('projects.edit');

        return view('projects.service-form', ['project' => $project, 'service' => $service->load(['hourLines', 'costLines']), ...$this->lookups()]);
    }

    public function update(Request $request, Project $project, ProjectService $service, BudgetMonitor $monitor): RedirectResponse
    {
        Gate::authorize('projects.edit');

        DB::transaction(function () use ($request, $service): void {
            $service->update($this->validated($request));
            $this->syncLines($service, $request);
        });

        $project->logEvent("Dienst \"{$service->name}\" gewijzigd door {$request->user()->name}.");
        $monitor->check($project);

        return redirect()->route('projects.show', [$project, 'tab' => 'services'])->with('status', 'Dienst opgeslagen.');
    }

    public function destroy(Request $request, Project $project, ProjectService $service): RedirectResponse
    {
        Gate::authorize('projects.edit');

        if ($service->timeEntries()->exists() || $service->purchaseLines()->exists()) {
            return back()->withErrors(['service' => 'Deze dienst heeft geregistreerde uren of kosten. Maak hem inactief in plaats van te verwijderen.']);
        }

        $service->delete();
        $project->logEvent("Dienst \"{$service->name}\" verwijderd door {$request->user()->name}.");

        return redirect()->route('projects.show', [$project, 'tab' => 'services'])->with('status', 'Dienst verwijderd.');
    }

    public function toggleActive(Project $project, ProjectService $service): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $service->update(['is_active' => ! $service->is_active]);

        return back()->with('status', $service->is_active ? 'Dienst geactiveerd.' : 'Dienst gedeactiveerd.');
    }

    public function progress(Request $request, Project $project, ProjectService $service): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $data = $request->validate(['completion_percentage' => ['required', 'integer', 'between:0,100']]);
        $service->update($data);

        return back()->with('status', 'Voortgang opgeslagen.');
    }

    public function move(Request $request, Project $project, ProjectService $service): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $direction = $request->input('direction') === 'up' ? -1 : 1;
        $siblings = $project->services()->get()->values();
        $index = $siblings->search(fn (ProjectService $s) => $s->is($service));
        $swap = $siblings[$index + $direction] ?? null;

        if ($swap) {
            DB::transaction(function () use ($siblings, $index, $swap, $service, $direction): void {
                $siblings->each(fn (ProjectService $s, int $i) => $s->update(['sort_order' => $i]));
                $service->update(['sort_order' => $index + $direction]);
                $swap->update(['sort_order' => $index]);
            });
        }

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'default_service_id' => ['nullable', 'exists:default_services,id'],
            'name' => ['required', 'string', 'max:255'],
            'invoice_method' => ['required', Rule::enum(InvoiceMethod::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'revenue_group_id' => ['nullable', 'exists:revenue_groups,id'],
            'vat_code' => ['nullable', Rule::in(array_keys(Organization::VAT_CODES))],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'invoice_from' => ['nullable', 'date'],
            'discount_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'completion_percentage' => ['nullable', 'integer', 'between:0,100'],
            'is_active' => ['nullable', 'boolean'],
            'hour_lines' => ['nullable', 'array'],
            'hour_lines.*.hour_type_id' => ['nullable', 'exists:hour_types,id'],
            'hour_lines.*.hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'hour_lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'cost_lines' => ['nullable', 'array'],
            'cost_lines.*.cost_type_id' => ['nullable', 'exists:cost_types,id'],
            'cost_lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'cost_lines.*.purchase_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'cost_lines.*.margin_percentage' => ['nullable', 'numeric', 'between:0,1000'],
        ], [], ['name' => 'omschrijving', 'invoice_method' => 'factuurmethode', 'end_date' => 'einddatum', 'price' => 'prijs', 'discount_percentage' => 'korting']);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['discount_percentage'] ??= 0;
        $data['completion_percentage'] ??= 0;

        if (in_array($data['invoice_method'], ['fixed_price', 'subscription'], true) === false) {
            $data['price'] = null;
        }

        return collect($data)->except(['hour_lines', 'cost_lines'])->all();
    }

    private function syncLines(ProjectService $service, Request $request): void
    {
        $service->hourLines()->delete();
        $service->costLines()->delete();

        foreach ((array) $request->input('hour_lines', []) as $line) {
            if (empty($line['hour_type_id']) || (float) ($line['hours'] ?? 0) <= 0) {
                continue;
            }

            $type = HourType::find($line['hour_type_id']);
            $service->hourLines()->create([
                'hour_type_id' => $type->id,
                'hours' => $line['hours'],
                'rate' => ($line['rate'] ?? '') !== '' ? $line['rate'] : $type->default_rate,
            ]);
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

        $service->update(['hours_alerted_at' => null, 'costs_alerted_at' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function lookups(): array
    {
        return [
            'defaultServices' => DefaultService::orderBy('name')->get(),
            'revenueGroups' => RevenueGroup::orderBy('name')->pluck('name', 'id'),
            'hourTypes' => HourType::orderBy('name')->get(),
            'costTypes' => CostType::orderBy('name')->get(),
            'methods' => InvoiceMethod::options(),
        ];
    }
}
