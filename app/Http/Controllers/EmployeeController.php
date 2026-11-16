<?php

namespace App\Http\Controllers;

use App\Enums\AbsenceType;
use App\Enums\BalanceKind;
use App\Enums\ContractType;
use App\Enums\DocumentCategory;
use App\Enums\EmploymentType;
use App\Enums\LeaveStatus;
use App\Enums\NoteType;
use App\Enums\Permission;
use App\Http\Requests\EmployeeRequest;
use App\Models\Absence;
use App\Models\Discipline;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Team;
use App\Models\UserGroup;
use App\Models\WorkSchedule;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        $sort = in_array($request->query('sort'), ['hired_at', 'job_title'], true) ? $request->query('sort') : 'last_name';
        $direction = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $employees = Employee::query()
            ->with(['team', 'manager', 'currentContract', 'discipline'])
            ->filter($request->query())
            ->orderBy($sort, $direction)
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        $employed = Employee::employed();

        return view('employees.index', [
            'employees' => $employees,
            'sort' => $sort,
            'direction' => $direction,
            'stats' => [
                'Actief' => (clone $employed)->count(),
                'Ziek vandaag' => Absence::whereDate('start_date', '<=', today())
                    ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
                    ->distinct('employee_id')->count('employee_id'),
                'Met verlof vandaag' => LeaveRequest::where('status', LeaveStatus::Approved->value)
                    ->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())
                    ->distinct('employee_id')->count('employee_id'),
                'Nieuw (30 dagen)' => Employee::whereDate('hired_at', '>=', today()->subDays(30))->count(),
            ],
            'teams' => Team::orderBy('name')->pluck('name', 'id'),
            'disciplines' => Discipline::orderBy('name')->pluck('name', 'id'),
            'managers' => Employee::whereHas('directReports')->orderBy('last_name')->get()->pluck('full_name', 'id'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Employee::class);

        return view('employees.form', ['employee' => new Employee(['is_active' => true, 'hired_at' => today()]), ...$this->formData()]);
    }

    public function store(EmployeeRequest $request, InvitationService $invitations): RedirectResponse
    {
        Gate::authorize('create', Employee::class);

        $employee = Employee::create($this->employeeData($request, new Employee));

        if ($employee->employee_number === null) {
            $employee->update(['employee_number' => $this->generateNumber($employee)]);
        }

        $status = 'Medewerker aangemaakt.';

        if ($request->boolean('invite') && Gate::allows(Permission::AccessManage->value)) {
            $invitations->invite($employee, $request->integer('user_group_id') ?: null);
            $status .= ' Uitnodiging verstuurd naar '.$employee->email.'.';
        }

        return redirect()->route('employees.show', $employee)->with('status', $status);
    }

    public function show(Employee $employee, Request $request): View
    {
        Gate::authorize('view', $employee);

        $employee->load(['team', 'manager', 'discipline', 'user.group', 'contracts', 'schedules', 'rates', 'directReports',
            'leaveRequests.leaveType', 'leaveRequests.decider', 'absences', 'documents.uploader', 'ledgerEntries', 'notes.author']);

        return view('employees.show', [
            'employee' => $employee,
            'balances' => ['vacation' => $employee->balance(BalanceKind::Vacation), 'tvt' => $employee->balance(BalanceKind::TimeForTime)],
            'currentSchedule' => $employee->scheduleOn(today()),
            'currentRate' => $employee->rateOn(today()),
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('id')->pluck('name', 'id'),
            'weekdays' => WorkSchedule::WEEKDAYS,
            'contractTypes' => ContractType::options(),
            'employmentTypes' => EmploymentType::options(),
            'absenceTypes' => AbsenceType::options(),
            'documentCategories' => DocumentCategory::options(),
            'noteTypes' => NoteType::options(),
            'ledgerActions' => [
                'allocation' => 'Verlofuren toekennen (vakantie)',
                'extra_hours' => 'Extra gewerkte uren (tijd-voor-tijd)',
                'conversion' => 'Tijd-voor-tijd omzetten naar vakantie-uren',
                'correction' => 'Correctie (+ of −)',
            ],
            'groups' => UserGroup::orderBy('name')->pluck('name', 'id'),
            'tab' => $request->query('tab'),
        ]);
    }

    public function edit(Employee $employee): View
    {
        Gate::authorize('update', $employee);

        return view('employees.form', ['employee' => $employee, ...$this->formData($employee)]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $employee->update($this->employeeData($request, $employee));
        $employee->user?->update(['name' => $employee->full_name, 'email' => $employee->email]);

        return redirect()->route('employees.show', $employee)->with('status', 'Medewerker opgeslagen.');
    }

    public function offboard(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $data = $request->validate([
            'left_at' => ['required', 'date', $employee->hired_at ? 'after_or_equal:'.$employee->hired_at->format('Y-m-d') : 'date'],
            'leave_reason' => ['nullable', 'string', 'max:255'],
        ], [], ['left_at' => 'datum uit dienst']);

        DB::transaction(function () use ($employee, $data): void {
            $leaving = today()->gte($data['left_at']);

            $employee->update([...$data, 'is_active' => ! $leaving]);
            $employee->contracts()->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>', $data['left_at']))
                ->update(['end_date' => $data['left_at']]);
            $employee->leaveRequests()->where('status', LeaveStatus::Pending->value)->whereDate('start_date', '>', $data['left_at'])
                ->update(['status' => LeaveStatus::Cancelled->value]);

            if ($leaving) {
                $employee->user?->update(['is_active' => false]);
            }
        });

        return redirect()->route('employees.show', $employee)->with('status', 'Medewerker uit dienst gemeld.');
    }

    public function reactivate(Employee $employee): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $employee->update(['left_at' => null, 'leave_reason' => null, 'is_active' => true]);
        $employee->user?->update(['is_active' => true]);

        return redirect()->route('employees.show', $employee)->with('status', 'Medewerker weer in dienst gemeld.');
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeData(EmployeeRequest $request, Employee $employee): array
    {
        $data = Arr::except($request->validated(), ['invite', 'user_group_id']);

        if (! Gate::allows('updatePrivate', $employee->exists ? $employee : new Employee)) {
            $data = Arr::except($data, Employee::PRIVATE_FIELDS);
        }

        return $data;
    }

    private function generateNumber(Employee $employee): ?string
    {
        $number = sprintf('M%04d', $employee->id);

        return Employee::where('employee_number', $number)->exists() ? null : $number;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Employee $employee = null): array
    {
        return [
            'teams' => Team::orderBy('name')->pluck('name', 'id'),
            'disciplines' => Discipline::orderBy('name')->pluck('name', 'id'),
            'managers' => Employee::employed()->when($employee, fn ($query) => $query->whereKeyNot($employee->id))
                ->orderBy('last_name')->get()->pluck('full_name', 'id'),
            'groups' => UserGroup::orderBy('name')->pluck('name', 'id'),
        ];
    }
}
