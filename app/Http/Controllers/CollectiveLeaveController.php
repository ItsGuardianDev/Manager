<?php

namespace App\Http\Controllers;

use App\Models\CollectiveLeave;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Team;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CollectiveLeaveController extends Controller
{
    public function index(): View
    {
        Gate::authorize('leave.manage');

        return view('leave.collective', [
            'collectives' => CollectiveLeave::with('leaveType')->withCount('requests')->orderByDesc('start_date')->paginate(10),
            'leaveTypes' => LeaveType::where('is_active', true)->pluck('name', 'id'),
            'teams' => Team::orderBy('name')->pluck('name', 'id'),
            'employees' => Employee::employed()->orderBy('last_name')->get(),
        ]);
    }

    public function store(Request $request, LeaveService $leave): RedirectResponse
    {
        Gate::authorize('leave.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'scope' => ['required', 'in:all,teams,employees'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['exists:teams,id'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['exists:employees,id'],
        ], [], ['name' => 'naam', 'leave_type_id' => 'verlofsoort', 'start_date' => 'startdatum', 'end_date' => 'einddatum']);

        $employees = Employee::employed()
            ->when($data['scope'] === 'teams', fn ($query) => $query->whereHas('teams', fn ($query) => $query->whereIn('teams.id', $data['team_ids'] ?? [])))
            ->when($data['scope'] === 'employees', fn ($query) => $query->whereIn('id', $data['employee_ids'] ?? []))
            ->get();

        if ($employees->isEmpty()) {
            return back()->withInput()->withErrors(['scope' => 'Er zijn geen medewerkers geselecteerd.']);
        }

        $collective = CollectiveLeave::create([
            'name' => $data['name'], 'leave_type_id' => $data['leave_type_id'], 'start_date' => $data['start_date'],
            'end_date' => $data['end_date'], 'created_by' => $request->user()->id,
        ]);

        $result = $leave->createCollective($collective, $employees, $request->user());

        return redirect()->route('collective-leave.index')
            ->with('status', "Collectief verlof geboekt voor {$result['created']} medewerkers.")
            ->with('collective_skipped', $result['skipped']);
    }
}
