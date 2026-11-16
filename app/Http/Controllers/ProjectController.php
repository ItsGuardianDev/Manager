<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceMethod;
use App\Enums\ProjectStatus;
use App\Models\DefaultService;
use App\Models\Employee;
use App\Models\HourType;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Project;
use App\Models\Team;
use App\Services\ProjectCopier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    private const SORTS = ['updated_at' => 'Datum update aflopend', 'name' => 'Naam', 'number' => 'Projectnummer', 'start_date' => 'Begindatum', 'created_at' => 'Datum aangemaakt'];

    public function index(Request $request): View
    {
        Gate::authorize('projects.view');

        $tab = in_array($request->query('tab'), ['closed', 'all'], true) ? $request->query('tab') : 'active';
        $sort = array_key_exists((string) $request->query('sort'), self::SORTS) ? $request->query('sort') : 'updated_at';

        $projects = Project::query()
            ->with(['organization', 'manager', ...Project::BUDGET_RELATIONS])
            ->inStatus($tab)
            ->filter($request->query())
            ->orderBy($sort, in_array($sort, ['name', 'number'], true) ? 'asc' : 'desc')
            ->paginate(15)
            ->withQueryString();

        $statsSource = Project::query()->with(Project::BUDGET_RELATIONS)->inStatus($tab)->filter($request->query())->get();

        return view('projects.index', [
            'projects' => $projects,
            'tab' => $tab,
            'sorts' => self::SORTS,
            'stats' => [
                'projects' => $statsSource->count(),
                'budget' => $statsSource->sum(fn (Project $p) => $p->totalBudget()),
                'spent' => $statsSource->sum(fn (Project $p) => $p->totalSpent()),
                'over' => $statsSource->filter(fn (Project $p) => $p->isOverBudget())->count(),
            ],
            'methods' => InvoiceMethod::options(),
            'managers' => Employee::whereHas('managedProjects')->orderBy('last_name')->get()->pluck('full_name', 'id'),
            'defaultServices' => DefaultService::orderBy('name')->pluck('name', 'id'),
            'employees' => Employee::employed()->orderBy('last_name')->get(),
            'customers' => Organization::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'nextNumber' => Project::nextNumber(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_invoiceable' => ['nullable', 'boolean'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'number' => ['nullable', 'string', 'max:30', 'unique:projects,number'],
        ], [], ['name' => 'projectnaam', 'organization_id' => 'bedrijf', 'number' => 'projectnummer']);

        $invoiceable = $request->boolean('is_invoiceable', true);

        if ($invoiceable && empty($data['organization_id'] ?? null)) {
            return back()->withInput()->withErrors(['organization_id' => 'Kies een bedrijf, of maak er een intern project van.']);
        }

        $project = Project::create([
            'number' => ($data['number'] ?? null) ?: Project::nextNumber(),
            'name' => $data['name'],
            'organization_id' => $invoiceable ? $data['organization_id'] : null,
            'manager_id' => $data['manager_id'] ?? null,
            'is_invoiceable' => $invoiceable,
            'start_date' => today(),
        ]);
        $project->logEvent('Project aangemaakt door '.$request->user()->name.'.');

        return redirect()->route('projects.show', [$project, 'tab' => 'services'])->with('status', 'Project aangemaakt. Voeg nu diensten toe.');
    }

    public function show(Project $project, Request $request): View
    {
        Gate::authorize('projects.view');

        $project->load([...Project::BUDGET_RELATIONS, 'services.hourLines.hourType', 'services.costLines.costType', 'services.revenueGroup', 'organization', 'person', 'manager',
            'members', 'teams', 'notes.author', 'documents.uploader', 'timeEntries.employee', 'timeEntries.service', 'timeEntries.hourType',
            'purchases.supplier', 'purchases.lines.service', 'purchases.lines.costType', 'copiedFrom']);

        return view('projects.show', [
            'project' => $project,
            'tab' => $request->query('tab'),
            'serviceFilter' => $request->query('services', 'active'),
            'customers' => Organization::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'contacts' => Person::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get()->pluck('full_name', 'id'),
            'employees' => Employee::employed()->orderBy('last_name')->get(),
            'teams' => Team::orderBy('name')->pluck('name', 'id'),
            'hourTypes' => HourType::orderBy('name')->pluck('name', 'id'),
            'registrableEmployees' => $this->registrableEmployees($request),
            'statuses' => ProjectStatus::options(),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:30', Rule::unique('projects', 'number')->ignore($project)],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'person_id' => ['nullable', 'exists:people,id'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'is_invoiceable' => ['nullable', 'boolean'],
            'is_on_hold' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'track_kilometers' => ['nullable', 'boolean'],
            'kilometer_price' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:employees,id'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['exists:teams,id'],
        ], [], ['name' => 'projectnaam', 'number' => 'projectnummer', 'organization_id' => 'bedrijf', 'end_date' => 'einddatum', 'start_date' => 'begindatum']);

        $invoiceable = $request->boolean('is_invoiceable', true);

        if ($invoiceable && empty($data['organization_id'])) {
            return back()->withInput()->withErrors(['organization_id' => 'Een te factureren project heeft een bedrijf nodig. Kies "Nee (intern project)" voor een intern project.']);
        }

        $project->update([
            ...collect($data)->except(['member_ids', 'team_ids'])->all(),
            'is_invoiceable' => $invoiceable,
            'is_on_hold' => $request->boolean('is_on_hold'),
            'track_kilometers' => $request->boolean('track_kilometers'),
            'organization_id' => $invoiceable ? $data['organization_id'] : ($data['organization_id'] ?? null),
        ]);

        $project->members()->sync($data['member_ids'] ?? []);
        $project->teams()->sync($data['team_ids'] ?? []);
        $project->logEvent('Projectinstellingen gewijzigd door '.$request->user()->name.'.');

        return redirect()->route('projects.show', [$project, 'tab' => 'settings'])->with('status', 'Projectinstellingen opgeslagen.');
    }

    /**
     * Close or complete a project once the work and the administration are done.
     */
    public function close(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $data = $request->validate(['status' => ['required', Rule::in(['completed', 'closed'])], 'force' => ['nullable', 'boolean']]);

        $project->load(Project::BUDGET_RELATIONS);
        $problems = [];

        if (($count = $project->unprocessedPurchaseCount()) > 0) {
            $problems[] = "Er staan nog {$count} inkoopboekingen te verwerken.";
        }

        $unfinished = $project->services->where('is_active', true)->where('completion_percentage', '<', 100);

        if ($unfinished->isNotEmpty() && ! $request->boolean('force')) {
            $problems[] = 'Niet alle diensten zijn 100% voltooid: '.$unfinished->pluck('name')->implode(', ').'. Vink "Toch sluiten" aan om door te gaan.';
        }

        if ($problems !== []) {
            return back()->withErrors(['close' => implode(' ', $problems)]);
        }

        $project->update(['status' => $data['status'], 'closed_at' => now()]);
        $project->logEvent('Project '.($data['status'] === 'closed' ? 'gesloten' : 'afgerond').' door '.$request->user()->name.'.');

        return redirect()->route('projects.show', $project)->with('status', $data['status'] === 'closed' ? 'Project gesloten.' : 'Project afgerond.');
    }

    public function reopen(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $project->update(['status' => ProjectStatus::Active, 'closed_at' => null]);
        $project->logEvent('Project heropend door '.$request->user()->name.'.');

        return back()->with('status', 'Project heropend.');
    }

    public function copy(Request $request, Project $project, ProjectCopier $copier): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:30', 'unique:projects,number'],
        ], [], ['name' => 'projectnaam', 'number' => 'projectnummer']);

        $copy = $copier->copy($project, $data['name'], $data['number'] ?? null);

        return redirect()->route('projects.show', [$copy, 'tab' => 'settings'])->with('status', 'Project gekopieerd.');
    }

    /**
     * @return Collection<int, Employee>
     */
    private function registrableEmployees(Request $request)
    {
        $user = $request->user();

        if ($user->can('projects.edit')) {
            return Employee::employed()->orderBy('last_name')->get();
        }

        return $user->employee && Gate::allows('projects.view') ? collect([$user->employee]) : collect();
    }
}
