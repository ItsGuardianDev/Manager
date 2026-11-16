<?php

namespace App\Http\Controllers;

use App\Enums\ContractType;
use App\Enums\EmploymentType;
use App\Models\Employee;
use App\Models\EmploymentContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmploymentContractController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manageContracts', $employee);

        $employee->contracts()->create($this->validated($request, $employee));

        return $this->back($employee, 'Contract toegevoegd.');
    }

    public function update(Request $request, Employee $employee, EmploymentContract $contract): RedirectResponse
    {
        Gate::authorize('manageContracts', $employee);

        $contract->update($this->validated($request, $employee, $contract));

        return $this->back($employee, 'Contract bijgewerkt.');
    }

    public function destroy(Employee $employee, EmploymentContract $contract): RedirectResponse
    {
        Gate::authorize('manageContracts', $employee);

        $contract->delete();

        return $this->back($employee, 'Contract verwijderd.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Employee $employee, ?EmploymentContract $contract = null): array
    {
        $data = $request->validate([
            'contract_type' => ['required', Rule::enum(ContractType::class)],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'hours_per_week' => ['nullable', 'numeric', 'between:0,80'],
            'gross_monthly_salary' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'contract_type' => 'contractsoort', 'employment_type' => 'dienstverband', 'start_date' => 'startdatum', 'end_date' => 'einddatum',
            'hours_per_week' => 'uren per week', 'gross_monthly_salary' => 'bruto maandsalaris',
        ]);

        if ($data['contract_type'] === ContractType::Fixed->value && empty($data['end_date'])) {
            throw ValidationException::withMessages(['end_date' => 'Een contract voor bepaalde tijd heeft een einddatum nodig.']);
        }

        $overlaps = $employee->contracts()
            ->when($contract, fn ($query) => $query->whereKeyNot($contract->id))
            ->whereDate('start_date', '<=', $data['end_date'] ?? '9999-12-31')
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $data['start_date']))
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['start_date' => 'Dit contract overlapt met een bestaand contract. Beëindig eerst het vorige contract.']);
        }

        return $data;
    }

    private function back(Employee $employee, string $status): RedirectResponse
    {
        return redirect()->route('employees.show', [$employee, 'tab' => 'contracts'])->with('status', $status);
    }
}
