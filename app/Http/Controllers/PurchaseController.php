<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseStatus;
use App\Models\CostType;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Purchase;
use App\Services\BudgetMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('projects.view');

        $tab = $request->query('tab') === 'processed' ? 'processed' : 'to_process';

        $purchases = Purchase::with(['project.organization', 'supplier', 'lines'])
            ->where('status', $tab)
            ->when($request->query('q'), fn ($query, $q) => $query->where(fn ($query) => $query->where('description', 'like', "%{$q}%")
                ->orWhereHas('project', fn ($query) => $query->where('name', 'like', "%{$q}%"))))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('purchases.index', [
            'purchases' => $purchases,
            'tab' => $tab,
            'total' => Purchase::with('lines')->where('status', $tab)->get()->sum(fn (Purchase $p) => $p->total()),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('projects.edit');

        $projects = Project::where('status', 'active')->with('services')->orderBy('name')->get();

        return view('purchases.form', [
            'projects' => $projects,
            'selected' => $request->query('project_id'),
            'suppliers' => Organization::where('is_active', true)->whereIn('type', ['supplier', 'partner', 'competitor', 'customer'])->orderBy('name')->pluck('name', 'id'),
            'costTypes' => CostType::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, BudgetMonitor $monitor): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'supplier_id' => ['nullable', 'exists:organizations,id'],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:invoice,expense'],
            'lines' => ['required', 'array'],
            'lines.*.project_service_id' => ['nullable', 'exists:project_services,id'],
            'lines.*.cost_type_id' => ['nullable', 'exists:cost_types,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [], ['project_id' => 'project', 'description' => 'omschrijving', 'date' => 'datum']);

        $project = Project::findOrFail($data['project_id']);

        $lines = collect($data['lines'])->filter(fn ($line) => ($line['unit_price'] ?? '') !== '');

        if ($lines->isEmpty()) {
            return back()->withInput()->withErrors(['lines' => 'Vul minimaal één regel met een prijs in.']);
        }

        $purchase = DB::transaction(function () use ($data, $lines, $project): Purchase {
            $purchase = $project->purchases()->create(collect($data)->except(['project_id', 'lines'])->all());

            foreach ($lines as $line) {
                $serviceId = $line['project_service_id'] ?? null;

                if ($serviceId && ! $project->services()->whereKey($serviceId)->exists()) {
                    $serviceId = null;
                }

                $purchase->lines()->create([
                    'project_service_id' => $serviceId,
                    'cost_type_id' => $line['cost_type_id'] ?? null,
                    'description' => $line['description'] ?? null,
                    'quantity' => ($line['quantity'] ?? '') !== '' ? $line['quantity'] : 1,
                    'unit_price' => $line['unit_price'],
                ]);
            }

            return $purchase;
        });

        $project->logEvent('Inkoop "'.$purchase->description.'" van € '.number_format($purchase->load('lines')->total(), 2, ',', '.').' geboekt door '.$request->user()->name.'.');
        $monitor->check($project);

        return redirect()->route('projects.show', [$project, 'tab' => 'costs'])->with('status', 'Inkoopboeking opgeslagen.');
    }

    public function process(Purchase $purchase): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $purchase->update(['status' => PurchaseStatus::Processed]);

        return back()->with('status', 'Inkoopboeking verwerkt.');
    }

    public function destroy(Request $request, Purchase $purchase, BudgetMonitor $monitor): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $project = $purchase->project;
        $purchase->delete();
        $project->logEvent('Inkoop "'.$purchase->description.'" verwijderd door '.$request->user()->name.'.');
        $monitor->check($project);

        return back()->with('status', 'Inkoopboeking verwijderd.');
    }
}
