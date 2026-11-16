<?php

namespace App\Http\Controllers;

use App\Models\CustomerGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerGroupController extends Controller
{
    public function index(): View
    {
        return view('customer-groups.index', ['groups' => CustomerGroup::withCount('organizations')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        CustomerGroup::create($this->validated($request));

        return back()->with('status', 'Klantgroep aangemaakt.');
    }

    public function update(Request $request, CustomerGroup $customerGroup): RedirectResponse
    {
        $customerGroup->update($this->validated($request, $customerGroup));

        return back()->with('status', 'Klantgroep opgeslagen.');
    }

    public function destroy(CustomerGroup $customerGroup): RedirectResponse
    {
        $customerGroup->delete();

        return back()->with('status', 'Klantgroep verwijderd.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CustomerGroup $group = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('customer_groups', 'name')->ignore($group)],
        ], [], ['name' => 'naam']);
    }
}
