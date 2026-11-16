<?php

namespace App\Http\Controllers;

use App\Enums\AbsenceType;
use App\Enums\BalanceKind;
use App\Enums\ContractType;
use App\Enums\DocumentCategory;
use App\Enums\EmployeeType;
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
use App\Models\Person;
use App\Models\Team;
use App\Models\UserGroup;
use App\Models\WorkSchedule;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        $sort = in_array($request->query('sort'), ['hired_at', 'job_title'], true) ? $request->query('sort') : 'last_name';
        $direction = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $employees = Employee::query()
            ->with(['teams', 'manager', 'currentContract', 'disciplines'])
            ->filter($request->query())
            ->orderBy($sort, $direction)
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        $employed = Employee::employed();

        $demographics = null;

        if (Gate::allows(Permission::EmployeesViewPrivate->value)) {
            $filtered = Employee::query()->filter($request->query())->get(['gender', 'date_of_birth']);
            $ages = $filtered->whereNotNull('date_of_birth')->map(fn (Employee $e) => $e->date_of_birth->age);
            $genders = ['female' => 'Vrouw', 'male' => 'Man', 'other' => 'Anders'];

            $demographics = [
                'average_age' => $ages->isNotEmpty() ? (int) round($ages->avg()) : null,
                'gender' => $filtered->groupBy(fn (Employee $e) => $genders[$e->gender] ?? 'Onbekend')->map->count()->sortDesc()->all(),
            ];
        }

        return view('employees.index', [
            'demographics' => $demographics,
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
            'employeeTypes' => EmployeeType::options(),
            'managers' => Employee::whereHas('directReports')->orderBy('last_name')->get()->pluck('full_name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Employee::class);

        $employee = new Employee(['is_active' => true, 'hired_at' => today(), 'employee_type' => EmployeeType::Own]);
        $person = $request->filled('person_id') ? Person::find($request->query('person_id')) : null;

        if ($person) {
            $employee->fill([
                'first_name' => $person->first_name, 'initials' => $person->initials, 'last_name_prefix' => $person->last_name_prefix,
                'last_name' => $person->last_name, 'email' => $person->email, 'phone' => $person->phone, 'mobile' => $person->mobile,
                'linkedin_url' => $person->linkedin_url, 'twitter_url' => $person->twitter_url, 'facebook_url' => $person->facebook_url,
            ]);
        }

        return view('employees.form', [
            'employee' => $employee,
            'person' => $person,
            'candidates' => Person::where('is_active', true)->whereDoesntHave('employee')->orderBy('last_name')->orderBy('first_name')->get(),
            ...$this->formData(),
        ]);
    }

    public function store(EmployeeRequest $request, InvitationService $invitations): RedirectResponse
    {
        Gate::authorize('create', Employee::class);

        $employee = Employee::create($this->employeeData($request, new Employee) + ['person_id' => $this->freePersonId($request)]);
        $this->syncRelations($employee, $request);
        $employee->logEvent('Medewerker aangemaakt door '.$request->user()->name.'.');

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

        $employee->load(['teams', 'manager', 'disciplines', 'user.group', 'contracts', 'schedules', 'directReports',
            'leaveRequests.leaveType', 'leaveRequests.decider', 'absences', 'documents.uploader', 'ledgerEntries', 'notes.author']);

        return view('employees.show', [
            'employee' => $employee,
            'balances' => ['vacation' => $employee->balance(BalanceKind::Vacation), 'tvt' => $employee->balance(BalanceKind::TimeForTime)],
            'currentSchedule' => $employee->scheduleOn(today()),
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('id')->pluck('name', 'id'),
            'weekdays' => WorkSchedule::WEEKDAYS,
            'contractTypes' => ContractType::options(),
            'employmentTypes' => EmploymentType::options(),
            'absenceTypes' => AbsenceType::options(),
            'documentCategories' => DocumentCategory::options(),
            'noteTypes' => NoteType::selectable(),
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

        return view('employees.form', ['employee' => $employee->load(['teams', 'disciplines']), 'person' => null, 'candidates' => collect(), ...$this->formData($employee)]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $employee->update($this->employeeData($request, $employee));
        $this->syncRelations($employee, $request);
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
            $employee->logEvent('Uit dienst gemeld per '.Carbon::parse($data['left_at'])->format('d-m-Y').($data['leave_reason'] ? ' ('.$data['leave_reason'].')' : '').'.');
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
        $employee->logEvent('Weer in dienst gemeld.');
        $employee->user?->update(['is_active' => true]);

        return redirect()->route('employees.show', $employee)->with('status', 'Medewerker weer in dienst gemeld.');
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeData(EmployeeRequest $request, Employee $employee): array
    {
        $data = Arr::except($request->validated(), ['invite', 'user_group_id', 'team_ids', 'discipline_ids', 'photo', 'person_id']);

        if (empty($data['employee_type'])) {
            unset($data['employee_type']);
        }

        if (! Gate::allows('updatePrivate', $employee->exists ? $employee : new Employee)) {
            $data = Arr::except($data, Employee::PRIVATE_FIELDS);
        }

        return $data;
    }

    private function syncRelations(Employee $employee, EmployeeRequest $request): void
    {
        $employee->teams()->sync($request->input('team_ids', []));
        $employee->disciplines()->sync($request->input('discipline_ids', []));

        if ($request->hasFile('photo')) {
            if ($employee->photo_path) {
                Storage::disk('local')->delete($employee->photo_path);
            }

            $employee->update(['photo_path' => $request->file('photo')->store('employee-photos', 'local')]);
        }
    }

    private function freePersonId(EmployeeRequest $request): ?int
    {
        $id = $request->integer('person_id') ?: null;

        return $id && ! Employee::where('person_id', $id)->exists() ? $id : null;
    }

    public function photo(Employee $employee): StreamedResponse
    {
        Gate::authorize('view', $employee);

        abort_unless($employee->photo_path && Storage::disk('local')->exists($employee->photo_path), 404);

        return Storage::disk('local')->response($employee->photo_path);
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
            'employeeTypes' => EmployeeType::options(),
            'maritalStatuses' => Employee::MARITAL_STATUSES,
            'managers' => Employee::employed()->when($employee, fn ($query) => $query->whereKeyNot($employee->id))
                ->orderBy('last_name')->get()->pluck('full_name', 'id'),
            'groups' => UserGroup::orderBy('name')->pluck('name', 'id'),
        ];
    }
}
