<?php

namespace App\Http\Controllers;

use App\Enums\ContractType;
use App\Enums\EmploymentType;
use App\Enums\NoteType;
use App\Enums\Permission;
use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\Discipline;
use App\Models\Employee;
use App\Models\Team;
use App\Models\WorkSchedule;
use App\Services\XlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeImportExportController extends Controller
{
    use HandlesCsv;

    private const PUBLIC_FIELDS = ['employee_number', 'first_name', 'initials', 'last_name_prefix', 'last_name', 'email', 'phone', 'mobile', 'job_title', 'team', 'discipline', 'manager_email', 'hired_at', 'left_at'];

    private const PRIVATE_FIELDS = ['gender', 'date_of_birth', 'private_email', 'street', 'zipcode', 'city', 'country', 'iban'];

    private const CONTRACT_FIELDS = ['contract_type', 'employment_type', 'contract_start', 'contract_end', 'hours_per_week'];

    private const FIELDS = [...self::PUBLIC_FIELDS, ...self::PRIVATE_FIELDS, ...self::CONTRACT_FIELDS];

    public function form(): View
    {
        Gate::authorize('create', Employee::class);

        return view('employees.import', [
            'fields' => self::FIELDS,
            'contractTypes' => array_keys(ContractType::options()),
            'employmentTypes' => array_keys(EmploymentType::options()),
        ]);
    }

    public function export(Request $request): StreamedResponse|BinaryFileResponse
    {
        Gate::authorize('viewAny', Employee::class);

        if ($request->query('format') !== 'csv') {
            return $this->exportWorkbook($request);
        }

        $private = Gate::allows(Permission::EmployeesViewPrivate->value);
        $contracts = Gate::allows(Permission::ContractsView->value);

        $headers = [...self::PUBLIC_FIELDS, ...($private ? self::PRIVATE_FIELDS : []), ...($contracts ? self::CONTRACT_FIELDS : [])];

        $employees = Employee::query()
            ->with(['teams', 'disciplines', 'manager', 'currentContract'])
            ->filter($request->query())
            ->orderBy('last_name')->orderBy('first_name')
            ->cursor();

        $rows = (function () use ($employees, $headers) {
            foreach ($employees as $employee) {
                $contract = $employee->currentContract;
                $values = [
                    ...$employee->only(array_merge(self::PUBLIC_FIELDS, self::PRIVATE_FIELDS)),
                    'team' => $employee->teams->pluck('name')->implode('|'),
                    'discipline' => $employee->disciplines->pluck('name')->implode('|'),
                    'manager_email' => $employee->manager?->email,
                    'hired_at' => $employee->hired_at?->format('Y-m-d'),
                    'left_at' => $employee->left_at?->format('Y-m-d'),
                    'date_of_birth' => $employee->date_of_birth?->format('Y-m-d'),
                    'contract_type' => $contract?->contract_type->value,
                    'employment_type' => $contract?->employment_type->value,
                    'contract_start' => $contract?->start_date->format('Y-m-d'),
                    'contract_end' => $contract?->end_date?->format('Y-m-d'),
                    'hours_per_week' => $contract?->hours_per_week,
                ];

                yield array_map(fn ($field) => $values[$field] ?? null, $headers);
            }
        })();

        return $this->streamCsv('medewerkers-'.now()->format('Y-m-d').'.csv', $headers, $rows);
    }

    /**
     * Excel workbook with a tab per subject; tabs are only included when the user may see that data.
     */
    private function exportWorkbook(Request $request): BinaryFileResponse
    {
        $private = Gate::allows(Permission::EmployeesViewPrivate->value);
        $employees = Employee::query()
            ->with(['teams', 'disciplines', 'manager', 'currentContract', 'contracts', 'schedules', 'leaveRequests.leaveType', 'ledgerEntries', 'absences', 'notes.author'])
            ->filter($request->query())->orderBy('last_name')->orderBy('first_name')->get();
        $yes = fn (bool $value) => $value ? 'Ja' : 'Nee';
        $each = fn (callable $map) => $employees->flatMap(fn (Employee $e) => $map($e));

        $headers = ['Personeelsnummer', 'Naam', 'Werk e-mail', 'Telefoon', 'Mobiel', 'Functie', 'Team', 'Discipline', 'Leidinggevende', 'In dienst', 'Uit dienst', 'Reden vertrek', 'In dienst nu'];
        if ($private) {
            array_push($headers, 'Geslacht', 'Geboortedatum', 'Privé e-mail', 'Adres', 'Postcode', 'Plaats', 'Land', 'Nationaliteit', 'IBAN', 'Noodcontact', 'Telefoon noodcontact');
        }

        $gender = ['male' => 'Man', 'female' => 'Vrouw', 'other' => 'Anders'];
        $main = $employees->map(fn (Employee $e) => [
            $e->employee_number, $e->full_name, $e->email, $e->phone, $e->mobile, $e->job_title, $e->teams->pluck('name')->implode(', '), $e->disciplines->pluck('name')->implode(', '),
            $e->manager?->full_name, $e->hired_at, $e->left_at, $e->leave_reason, $yes($e->is_employed),
            ...($private ? [$gender[$e->gender] ?? null, $e->date_of_birth, $e->private_email, $e->street, $e->zipcode, $e->city, $e->country,
                $e->nationality, $e->iban, $e->emergency_contact_name, $e->emergency_contact_phone] : []),
        ]);

        $book = XlsxWriter::make()->sheet('Medewerkers', $headers, $main);

        if (Gate::allows(Permission::ContractsView->value)) {
            $book->sheet('Contracten', ['Medewerker', 'Contractsoort', 'Dienstverband', 'Startdatum', 'Einddatum', 'Uren per week', 'Bruto maandsalaris', 'Opmerking'],
                $each(fn (Employee $e) => $e->contracts->map(fn ($c) => [
                    $e->full_name, $c->contract_type->label(), $c->employment_type->label(), $c->start_date, $c->end_date,
                    $c->hours_per_week !== null ? (float) $c->hours_per_week : null, $c->gross_monthly_salary !== null ? (float) $c->gross_monthly_salary : null, $c->remarks,
                ])));

            $days = WorkSchedule::WEEKDAYS;
            $book->sheet('Roosters', ['Medewerker', 'Begindatum', 'Einddatum', 'Type', 'Week', ...array_map(fn ($d) => $d.' (uren)', $days), 'Uren per week', 'Kostprijs per uur', 'Verkooptarief per uur'],
                $each(fn (Employee $e) => $e->schedules->flatMap(fn (WorkSchedule $s) => collect($s->alternating ? ['odd' => 'Oneven weken', 'even' => 'Even weken'] : ['odd' => 'Elke week'])
                    ->map(fn ($label, $week) => [
                        $e->full_name, $s->effective_from, $e->scheduleEnd($s), $s->alternating ? 'Even/oneven' : 'Elke week', $label,
                        ...array_map(fn ($d) => WorkSchedule::dayHours($s->pattern[$week][$d] ?? null), array_keys($days)), $s->weeklyHours($week),
                        $s->cost_rate !== null ? (float) $s->cost_rate : null, $s->sales_rate !== null ? (float) $s->sales_rate : null,
                    ])->values())));

        }

        if (Gate::allows(Permission::LeaveManage->value) || Gate::allows(Permission::LeaveApprove->value)) {
            $book->sheet('Verlofaanvragen', ['Medewerker', 'Soort', 'Van', 'Tot en met', 'Uren', 'Status', 'Toelichting', 'Beslissing'],
                $each(fn (Employee $e) => $e->leaveRequests->map(fn ($r) => [
                    $e->full_name, $r->leaveType->name, $r->start_date, $r->end_date, (float) $r->hours, $r->status->label(), $r->note, $r->decision_note,
                ])));
            $book->sheet('Verlofsaldo', ['Medewerker', 'Datum', 'Saldo', 'Reden', 'Omschrijving', 'Uren'],
                $each(fn (Employee $e) => $e->ledgerEntries->map(fn ($l) => [$e->full_name, $l->entry_date, $l->kind->label(), $l->reason->label(), $l->description, (float) $l->hours])));
        }

        if (Gate::allows(Permission::AbsenceManage->value)) {
            $book->sheet('Verzuim', ['Medewerker', 'Soort', 'Eerste ziektedag', 'Hersteld op', 'Percentage', 'Opmerking'],
                $each(fn (Employee $e) => $e->absences->map(fn ($a) => [$e->full_name, $a->type->label(), $a->start_date, $a->end_date, $a->percentage, $a->note])));
        }

        if (Gate::allows(Permission::EmployeesEdit->value)) {
            $book->sheet('Tijdlijn', ['Medewerker', 'Datum', 'Soort', 'Auteur', 'Bericht'],
                $each(fn (Employee $e) => $e->notes->map(fn ($n) => [$e->full_name, $n->created_at, NoteType::tryFrom($n->type)?->label(), $n->author?->name, $n->body])));
        }

        return $book->download('medewerkers-'.now()->format('Y-m-d').'.xlsx');
    }

    public function template(): StreamedResponse
    {
        Gate::authorize('create', Employee::class);

        $example = [
            'employee_number' => 'M1001', 'first_name' => 'Sanne', 'initials' => 'S.', 'last_name_prefix' => 'van', 'last_name' => 'Dijk',
            'email' => 'sanne@bedrijf.nl', 'phone' => '020 123 4567', 'mobile' => '06 12345678', 'job_title' => 'Projectmanager',
            'team' => 'Projecten', 'discipline' => 'Projectmanagement', 'manager_email' => '', 'hired_at' => '2024-03-01', 'left_at' => '',
            'gender' => 'female', 'date_of_birth' => '1990-05-17', 'private_email' => '', 'street' => 'Keizersgracht 1', 'zipcode' => '1015 AA',
            'city' => 'Amsterdam', 'country' => 'Nederland', 'iban' => '', 'contract_type' => 'indefinite', 'employment_type' => 'full_time',
            'contract_start' => '2024-03-01', 'contract_end' => '', 'hours_per_week' => '40',
        ];

        return $this->streamCsv('medewerkers-sjabloon.csv', self::FIELDS, [array_map(fn ($field) => $example[$field], self::FIELDS)]);
    }

    public function import(Request $request): RedirectResponse
    {
        Gate::authorize('create', Employee::class);

        $request->validate(['file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:5120']]);

        $rows = $this->readCsv($request->file('file'), self::FIELDS);

        if ($rows === [] || ! array_key_exists('last_name', reset($rows)) || ! array_key_exists('email', reset($rows))) {
            return back()->withErrors(['file' => 'Het bestand is leeg of mist de kolommen "last_name" en "email". Download het sjabloon voor het juiste formaat.']);
        }

        $canPrivate = Gate::allows(Permission::EmployeesEditPrivate->value);
        $canContracts = Gate::allows(Permission::ContractsEdit->value);
        $result = ['created' => 0, 'skipped' => [], 'errors' => []];
        $managers = [];

        foreach ($rows as $line => $row) {
            $data = [];
            foreach (array_merge(self::PUBLIC_FIELDS, self::PRIVATE_FIELDS) as $field) {
                $data[$field] = $this->nullIfBlank($row[$field] ?? null);
            }
            $data['email'] = $data['email'] ? strtolower($data['email']) : null;
            $data['iban'] = $data['iban'] ? strtoupper(preg_replace('/\s+/', '', $data['iban'])) : null;
            $data['gender'] = $data['gender'] ? strtolower($data['gender']) : null;

            $validator = Validator::make($data + $row, [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email:rfc', 'max:255'],
                'hired_at' => ['nullable', 'date'],
                'left_at' => ['nullable', 'date'],
                'date_of_birth' => ['nullable', 'date', 'before:today'],
                'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
                'contract_type' => ['nullable', Rule::enum(ContractType::class)],
                'employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
                'contract_start' => ['nullable', 'date'],
                'contract_end' => ['nullable', 'date'],
                'hours_per_week' => ['nullable', 'numeric', 'between:0,80'],
            ]);

            if ($validator->fails()) {
                $result['errors'][] = "Regel {$line}: ".$validator->errors()->first();

                continue;
            }

            $duplicate = Employee::where('email', $data['email'])
                ->when($data['employee_number'], fn ($query, $number) => $query->orWhere('employee_number', $number))
                ->first();

            if ($duplicate) {
                $result['skipped'][] = "Regel {$line}: {$duplicate->full_name} (e-mail of personeelsnummer bestaat al)";

                continue;
            }

            $teamName = $data['team'];
            $disciplineName = $data['discipline'];
            $managerEmail = $data['manager_email'];

            unset($data['team'], $data['discipline'], $data['manager_email']);

            if (! $canPrivate) {
                $data = array_diff_key($data, array_flip(self::PRIVATE_FIELDS));
            }

            $employee = Employee::create($data + [
                'is_active' => empty($data['left_at']) || $data['left_at'] >= today()->format('Y-m-d'),
            ]);

            if ($employee->employee_number === null) {
                $employee->update(['employee_number' => sprintf('M%04d', $employee->id)]);
            }

            $names = fn (?string $value) => array_values(array_filter(array_map('trim', explode('|', (string) $value))));
            $employee->teams()->sync(array_map(fn ($name) => Team::firstOrCreate(['name' => $name])->id, $names($teamName)));
            $employee->disciplines()->sync(array_map(fn ($name) => Discipline::firstOrCreate(['name' => $name])->id, $names($disciplineName)));

            if ($managerEmail) {
                $managers[$employee->id] = [$line, $managerEmail];
            }

            if ($canContracts && ! empty($row['contract_type'])) {
                $start = $this->nullIfBlank($row['contract_start'] ?? null) ?? $data['hired_at'];

                if ($start) {
                    $employee->contracts()->create([
                        'contract_type' => $row['contract_type'],
                        'employment_type' => $this->nullIfBlank($row['employment_type'] ?? null) ?? EmploymentType::FullTime->value,
                        'start_date' => $start,
                        'end_date' => $this->nullIfBlank($row['contract_end'] ?? null),
                        'hours_per_week' => $this->nullIfBlank($row['hours_per_week'] ?? null),
                    ]);
                } else {
                    $result['errors'][] = "Regel {$line}: contract overgeslagen, er is geen startdatum of datum in dienst.";
                }
            }

            $result['created']++;
        }

        foreach ($managers as $employeeId => [$line, $email]) {
            $manager = $this->findEmployee($email);

            if ($manager && $manager->id !== $employeeId) {
                Employee::whereKey($employeeId)->update(['manager_id' => $manager->id]);
            } else {
                $result['errors'][] = "Regel {$line}: leidinggevende \"{$email}\" niet gevonden.";
            }
        }

        return redirect()->route('employees.import')->with('import_result', $result);
    }
}
