<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationType;
use App\Http\Requests\OrganizationRequest;
use App\Models\CompanySize;
use App\Models\Industry;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    private const SORTABLE = ['name', 'type', 'visit_city', 'created_at'];

    public function index(Request $request): View
    {
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'name';
        $direction = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $filters = $request->query();

        $organizations = Organization::query()
            ->with(['industry', 'companySize', 'relationManager'])
            ->withCount('people')
            ->filter($filters)
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('organizations.index', [
            'organizations' => $organizations,
            'sort' => $sort,
            'direction' => $direction,
            'charts' => $this->charts($filters),
            ...$this->lookups(),
        ]);
    }

    public function create(): View
    {
        return view('organizations.form', [
            'organization' => new Organization(['type' => OrganizationType::Customer, 'is_active' => true, 'payment_term_days' => 30, 'visit_country' => 'Nederland']),
            ...$this->lookups(),
        ]);
    }

    public function store(OrganizationRequest $request): RedirectResponse
    {
        $organization = Organization::create($request->safe()->except(['note', 'postal_differs']));

        if ($request->filled('note')) {
            $organization->notes()->create(['body' => $request->input('note'), 'user_id' => $request->user()->id]);
        }

        return redirect()->route('organizations.show', $organization)->with('status', 'Bedrijf aangemaakt.');
    }

    public function show(Organization $organization): View
    {
        $organization->load(['industry', 'companySize', 'relationManager', 'notes.author', 'people']);

        return view('organizations.show', [
            'organization' => $organization,
            'availablePeople' => Person::query()
                ->where('is_active', true)
                ->whereNotIn('id', $organization->people->modelKeys())
                ->orderBy('last_name')->orderBy('first_name')
                ->get(),
        ]);
    }

    public function edit(Organization $organization): View
    {
        return view('organizations.form', ['organization' => $organization, ...$this->lookups()]);
    }

    public function update(OrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update($request->safe()->except(['note', 'postal_differs']));

        return redirect()->route('organizations.show', $organization)->with('status', 'Bedrijf bijgewerkt.');
    }

    /**
     * Companies are never hard-deleted; this toggles the active flag.
     */
    public function toggleActive(Organization $organization): RedirectResponse
    {
        $organization->update(['is_active' => ! $organization->is_active]);

        return back()->with('status', $organization->is_active ? 'Bedrijf geactiveerd.' : 'Bedrijf gedeactiveerd.');
    }

    /**
     * Distribution of the currently filtered companies, for the donut charts.
     *
     * @param  array<string, mixed>  $filters
     * @return array{type: array<string, int>, industry: array<string, int>, size: array<string, int>}
     */
    private function charts(array $filters): array
    {
        $count = fn (string $column) => Organization::query()->filter($filters)
            ->selectRaw("{$column} as bucket, count(*) as total")->groupBy($column)->orderByDesc('total')->pluck('total', 'bucket');

        return [
            'type' => $count('type')->mapWithKeys(fn ($total, $type) => [OrganizationType::from($type)->label() => $total])->all(),
            'industry' => $count('industry_id')->mapWithKeys(fn ($total, $id) => [Industry::find($id)?->name ?? 'Onbekend' => $total])->all(),
            'size' => $count('company_size_id')->mapWithKeys(fn ($total, $id) => [CompanySize::find($id)?->name ?? 'Onbekend' => $total])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lookups(): array
    {
        return [
            'types' => OrganizationType::cases(),
            'industries' => Industry::orderBy('name')->get(),
            'sizes' => CompanySize::orderBy('sort_order')->get(),
            'employees' => User::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
