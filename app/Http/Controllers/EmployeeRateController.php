<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmployeeRateController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manageContracts', $employee);

        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'cost_rate' => ['required', 'numeric', 'min:0', 'max:9999'],
            'sales_rate' => ['required', 'numeric', 'min:0', 'max:9999'],
        ], [], ['effective_from' => 'ingangsdatum', 'cost_rate' => 'kostprijs', 'sales_rate' => 'verkooptarief']);

        $employee->rates()->updateOrCreate(['effective_from' => $data['effective_from']], $data);

        return redirect()->route('employees.show', [$employee, 'tab' => 'schedule'])->with('status', 'Tarief opgeslagen.');
    }

    public function destroy(Employee $employee, EmployeeRate $rate): RedirectResponse
    {
        Gate::authorize('manageContracts', $employee);

        $rate->delete();

        return redirect()->route('employees.show', [$employee, 'tab' => 'schedule'])->with('status', 'Tarief verwijderd.');
    }
}
