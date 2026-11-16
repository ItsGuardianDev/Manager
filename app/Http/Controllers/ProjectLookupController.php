<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceMethod;
use App\Models\CostType;
use App\Models\DefaultService;
use App\Models\HourType;
use App\Models\RevenueGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lists the project module draws from: hour types, cost types, revenue groups and standard services.
 */
class ProjectLookupController extends Controller
{
    /**
     * @var array<string, array{model: class-string<Model>, title: string, extra?: string, extra_label?: string}>
     */
    private const TYPES = [
        'hour-types' => ['model' => HourType::class, 'title' => 'Urensoorten', 'extra' => 'default_rate', 'extra_label' => 'Standaard uurtarief (€)'],
        'cost-types' => ['model' => CostType::class, 'title' => 'Kostensoorten', 'extra' => 'default_margin', 'extra_label' => 'Standaard marge (%)'],
        'revenue-groups' => ['model' => RevenueGroup::class, 'title' => 'Omzetgroepen'],
        'default-services' => ['model' => DefaultService::class, 'title' => 'Standaarddiensten', 'extra' => 'invoice_method', 'extra_label' => 'Factuurmethode'],
    ];

    public function index(): View
    {
        Gate::authorize('projects.view');

        return view('projects.settings', [
            'sections' => collect(self::TYPES)->map(fn (array $type, string $key) => $type + ['key' => $key, 'items' => $type['model']::orderBy('name')->get()]),
            'methods' => InvoiceMethod::options(),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $config = $this->config($type);
        $config['model']::create($this->validated($request, $type));

        return back()->with('status', 'Toegevoegd.');
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        Gate::authorize('projects.edit');

        $item = $this->config($type)['model']::findOrFail($id);
        $item->update($this->validated($request, $type, $item));

        return back()->with('status', 'Opgeslagen.');
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        Gate::authorize('projects.edit');

        try {
            $this->config($type)['model']::findOrFail($id)->delete();
        } catch (QueryException) {
            return back()->withErrors(['lookup' => 'Dit item is in gebruik en kan niet worden verwijderd.']);
        }

        return back()->with('status', 'Verwijderd.');
    }

    /**
     * @return array{model: class-string<Model>, title: string, extra?: string}
     */
    private function config(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $type, ?Model $item = null): array
    {
        $config = $this->config($type);
        $table = (new $config['model'])->getTable();

        $rules = ['name' => ['required', 'string', 'max:100', Rule::unique($table, 'name')->ignore($item)]];

        if (($config['extra'] ?? null) === 'default_rate') {
            $rules['default_rate'] = ['nullable', 'numeric', 'min:0', 'max:9999'];
        } elseif (($config['extra'] ?? null) === 'default_margin') {
            $rules['default_margin'] = ['nullable', 'numeric', 'between:0,1000'];
        } elseif (($config['extra'] ?? null) === 'invoice_method') {
            $rules['invoice_method'] = ['nullable', Rule::enum(InvoiceMethod::class)];
        }

        $data = $request->validate($rules, [], ['name' => 'naam']);

        foreach (['default_rate', 'default_margin'] as $field) {
            if (array_key_exists($field, $rules)) {
                $data[$field] = $data[$field] ?? 0;
            }
        }

        return $data;
    }
}
