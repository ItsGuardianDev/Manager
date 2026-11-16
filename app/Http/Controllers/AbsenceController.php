<?php

namespace App\Http\Controllers;

use App\Enums\AbsenceType;
use App\Models\Absence;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AbsenceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('absence.manage');

        $status = $request->query('status', 'ongoing');

        return view('absences.index', [
            'absences' => Absence::with('employee.teams')
                ->when($status === 'ongoing', fn (Builder $query) => $query->whereNull('end_date'))
                ->when($status === 'closed', fn (Builder $query) => $query->whereNotNull('end_date'))
                ->orderByDesc('start_date')
                ->paginate(15)
                ->withQueryString(),
            'status' => $status,
            'employees' => Employee::employed()->orderBy('last_name')->get(),
            'absenceTypes' => AbsenceType::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'type' => ['required', Rule::enum(AbsenceType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'percentage' => ['required', 'integer', 'between:1,100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], ['employee_id' => 'medewerker', 'type' => 'soort', 'start_date' => 'startdatum', 'end_date' => 'einddatum', 'percentage' => 'percentage']);

        $employee = Employee::findOrFail($data['employee_id']);
        Gate::authorize('manageAbsence', $employee);

        $overlaps = $employee->absences()
            ->whereDate('start_date', '<=', $data['end_date'] ?? '9999-12-31')
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $data['start_date']))
            ->exists();

        if ($overlaps) {
            return back()->withInput()->withErrors(['start_date' => 'Deze medewerker heeft al een verzuimmelding in deze periode.']);
        }

        $employee->absences()->create([...$data, 'reported_by' => $request->user()->id]);

        return back()->with('status', 'Verzuim geregistreerd.');
    }

    public function update(Request $request, Absence $absence): RedirectResponse
    {
        Gate::authorize('manageAbsence', $absence->employee);

        $data = $request->validate([
            'end_date' => ['nullable', 'date', 'after_or_equal:'.$absence->start_date->format('Y-m-d')],
            'percentage' => ['nullable', 'integer', 'between:1,100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], ['end_date' => 'einddatum']);

        $absence->update(array_filter($data, fn ($value) => $value !== null) + ['end_date' => $data['end_date'] ?? null]);

        return back()->with('status', 'Verzuim bijgewerkt.');
    }

    public function destroy(Absence $absence): RedirectResponse
    {
        Gate::authorize('manageAbsence', $absence->employee);

        $absence->delete();

        return back()->with('status', 'Verzuimmelding verwijderd.');
    }
}
