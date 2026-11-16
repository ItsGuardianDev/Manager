<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Enums\Permission;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function __construct(private LeaveService $leave) {}

    /**
     * Approval inbox: everything for approvers, only direct reports for managers.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isManager = Employee::where('manager_id', $user->employee?->id)->exists();

        abort_unless($isManager || $user->hasPermission(Permission::LeaveApprove) || $user->hasPermission(Permission::LeaveManage), 403);

        $status = $request->query('status', 'pending');
        $seesAll = $user->hasPermission(Permission::LeaveApprove) || $user->hasPermission(Permission::LeaveManage);

        $requests = LeaveRequest::query()
            ->with(['employee.team', 'leaveType', 'decider'])
            ->when(! $seesAll, fn (Builder $query) => $query->whereHas('employee', fn (Builder $query) => $query->where('manager_id', $user->employee?->id)))
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->orderBy($status === 'pending' ? 'start_date' : 'decided_at', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('leave.index', ['requests' => $requests, 'status' => $status, 'statuses' => LeaveStatus::options()]);
    }

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('requestLeave', $employee);

        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'hours' => ['nullable', 'numeric', 'gt:0', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], ['leave_type_id' => 'verlofsoort', 'start_date' => 'startdatum', 'end_date' => 'einddatum', 'hours' => 'uren']);

        $from = Carbon::parse($data['start_date']);
        $to = Carbon::parse($data['end_date']);

        $employee->load('schedules');
        $hours = isset($data['hours']) ? (float) $data['hours'] : $employee->scheduledHours($from, $to);

        if ($hours <= 0) {
            throw ValidationException::withMessages(['hours' => 'Er zijn geen werkuren in deze periode. Vul zelf het aantal uren in of stel eerst een rooster in.']);
        }

        if ($this->leave->overlaps($employee, $from, $to)) {
            throw ValidationException::withMessages(['start_date' => 'Er is al een aanvraag of verlof in deze periode.']);
        }

        $this->leave->request($employee, LeaveType::findOrFail($data['leave_type_id']), $from, $to, $hours, $data['note'] ?? null);

        return redirect()->route('employees.show', [$employee, 'tab' => 'leave'])->with('status', 'Verlofaanvraag ingediend.');
    }

    public function decide(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        Gate::authorize('approveLeave', $leaveRequest->employee);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless($leaveRequest->status === LeaveStatus::Pending, 409, 'Deze aanvraag is al behandeld.');

        if ($data['decision'] === 'approve') {
            $this->leave->approve($leaveRequest, $request->user(), $data['decision_note'] ?? null);
            $status = 'Verlofaanvraag goedgekeurd.';
        } else {
            $this->leave->reject($leaveRequest, $request->user(), $data['decision_note'] ?? null);
            $status = 'Verlofaanvraag afgewezen.';
        }

        return back()->with('status', $status);
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $employee = $leaveRequest->employee;

        abort_unless(Gate::allows('requestLeave', $employee) || Gate::allows('manageLeave', $employee), 403);
        abort_unless(in_array($leaveRequest->status, [LeaveStatus::Pending, LeaveStatus::Approved], true), 409);

        $this->leave->cancel($leaveRequest);

        return back()->with('status', 'Verlof ingetrokken.');
    }
}
