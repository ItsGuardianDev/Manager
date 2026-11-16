<?php

namespace Tests\Feature;

use App\Enums\BalanceKind;
use App\Enums\Permission;
use App\Models\Absence;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Team;
use App\Models\User;
use App\Models\UserGroup;
use App\Notifications\EmployeeInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeesModuleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['user_group_id' => UserGroup::where('name', 'Beheerder')->value('id')]);
    }

    /**
     * @param  array<int, Permission>  $permissions
     */
    private function userWith(array $permissions): User
    {
        return User::factory()->create(['user_group_id' => UserGroup::factory()->with($permissions)->create()->id]);
    }

    private function weekdaySchedule(Employee $employee, string $from = '2020-01-01'): void
    {
        $day = ['start' => '09:00', 'end' => '17:00', 'break' => 0];
        $employee->schedules()->create(['effective_from' => $from, 'alternating' => false, 'pattern' => ['odd' => array_fill_keys([1, 2, 3, 4, 5], $day)]]);
    }

    public function test_access_requires_login_and_permission(): void
    {
        $this->get(route('employees.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get(route('employees.index'))->assertForbidden();
        $this->actingAs($this->userWith([Permission::EmployeesView]))->get(route('employees.index'))->assertOk();
    }

    public function test_employee_is_created_with_number_and_duplicates_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('employees.store'), [
            'first_name' => 'Sanne', 'last_name' => 'Dijk', 'email' => 'Sanne@Bedrijf.nl', 'bsn' => '111222333', 'iban' => 'nl91 abna 0417 1643 00',
        ])->assertRedirect();

        $employee = Employee::firstOrFail();
        $this->assertSame('sanne@bedrijf.nl', $employee->email);
        $this->assertSame(sprintf('M%04d', $employee->id), $employee->employee_number);
        $this->assertSame('NL91ABNA0417164300', $employee->iban);

        $this->actingAs($admin)->post(route('employees.store'), ['first_name' => 'Andere', 'last_name' => 'Persoon', 'email' => 'sanne@bedrijf.nl'])
            ->assertSessionHasErrors('email');
        $this->actingAs($admin)->post(route('employees.store'), ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'x@y.nl', 'bsn' => '123456789'])
            ->assertSessionHasErrors('bsn');
    }

    public function test_inviting_creates_a_user_who_can_activate_the_account(): void
    {
        Notification::fake();
        $group = UserGroup::where('name', 'Medewerker')->firstOrFail();

        $this->actingAs($this->admin())->post(route('employees.store'), [
            'first_name' => 'Nieuw', 'last_name' => 'Persoon', 'email' => 'nieuw@bedrijf.nl', 'invite' => 1, 'user_group_id' => $group->id,
        ])->assertRedirect();

        $employee = Employee::firstOrFail();
        $user = $employee->user;
        $this->assertNotNull($user);
        $this->assertSame($group->id, $user->user_group_id);

        $token = null;
        Notification::assertSentTo($user, EmployeeInvitation::class, function (EmployeeInvitation $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        auth()->logout();
        $this->get(route('invitation.show', ['token' => 'wrong', 'email' => $user->email]))->assertNotFound();
        $this->post(route('invitation.store', $token), ['email' => $user->email, 'password' => 'een-goed-wachtwoord', 'password_confirmation' => 'een-goed-wachtwoord'])
            ->assertRedirect(route('employees.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->invitation_token);
    }

    public function test_private_data_is_protected_by_permissions(): void
    {
        $employee = Employee::factory()->create(['bsn' => '111222333', 'iban' => 'NL91ABNA0417164300']);
        $viewer = $this->userWith([Permission::EmployeesView, Permission::EmployeesEdit]);

        $this->actingAs($viewer)->get(route('employees.show', $employee))->assertOk()->assertDontSee('111222333')->assertDontSee('NL91ABNA0417164300');
        $this->actingAs($this->userWith([Permission::EmployeesView, Permission::EmployeesViewPrivate]))
            ->get(route('employees.show', $employee))->assertSee('111222333');

        $this->actingAs($viewer)->put(route('employees.update', $employee), [
            'first_name' => $employee->first_name, 'last_name' => $employee->last_name, 'email' => $employee->email, 'bsn' => '999999990', 'job_title' => 'Nieuw',
        ])->assertRedirect();

        $employee->refresh();
        $this->assertSame('Nieuw', $employee->job_title);
        $this->assertSame('111222333', $employee->bsn);
    }

    public function test_employee_can_view_own_record_but_not_others(): void
    {
        $user = $this->userWith([]);
        $own = Employee::factory()->create(['user_id' => $user->id, 'bsn' => '111222333']);
        $other = Employee::factory()->create();

        $this->actingAs($user)->get(route('employees.show', $own))->assertOk()->assertSee('111222333');
        $this->actingAs($user)->get(route('employees.show', $other))->assertForbidden();
    }

    public function test_contracts_keep_history_and_cannot_overlap(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create();
        $contract = ['contract_type' => 'fixed', 'employment_type' => 'full_time', 'hours_per_week' => 40];

        $this->actingAs($admin)->post(route('employees.contracts.store', $employee), [...$contract, 'start_date' => '2024-01-01', 'end_date' => '2024-12-31'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('employees.contracts.store', $employee), [...$contract, 'start_date' => '2024-06-01', 'end_date' => '2025-06-01'])->assertSessionHasErrors('start_date');
        $this->actingAs($admin)->post(route('employees.contracts.store', $employee), [...$contract, 'start_date' => '2025-01-01'])->assertSessionHasErrors('end_date');
        $this->actingAs($admin)->post(route('employees.contracts.store', $employee), [...$contract, 'contract_type' => 'indefinite', 'start_date' => '2025-01-01'])->assertSessionHasNoErrors();

        $this->assertSame(2, $employee->contracts()->count());
        $this->assertSame('indefinite', $employee->currentContract->contract_type->value);
    }

    public function test_alternating_schedule_and_future_changes_use_effective_dates(): void
    {
        $employee = Employee::factory()->create();
        $day = ['start' => '09:00', 'end' => '17:30', 'break' => 30];

        $this->actingAs($this->admin())->post(route('employees.schedules.store', $employee), [
            'effective_from' => '2025-01-01', 'alternating' => 1,
            'days' => ['odd' => [1 => $day, 2 => $day], 'even' => [3 => $day, 4 => $day, 5 => $day]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('employees.schedules.store', $employee), [
            'effective_from' => '2030-01-01', 'days' => ['odd' => [1 => $day]],
        ])->assertSessionHasNoErrors();

        $employee->load('schedules');
        $schedule = $employee->scheduleOn(now()->setDate(2026, 3, 2));

        $this->assertSame(16.0, $schedule->weeklyHours('odd'));
        $this->assertSame(24.0, $schedule->weeklyHours('even'));
        // 2 March 2026 is ISO week 10 (even) → Monday has no hours; 9 March is week 11 (odd).
        $this->assertSame(0.0, $schedule->hoursOn(now()->setDate(2026, 3, 2)));
        $this->assertSame(8.0, $schedule->hoursOn(now()->setDate(2026, 3, 9)));
        $this->assertSame(8.0, $employee->scheduleOn(now()->setDate(2031, 1, 1))->weeklyHours('odd'));
    }

    public function test_rates_apply_from_their_effective_date(): void
    {
        $employee = Employee::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('employees.rates.store', $employee), ['effective_from' => '2025-01-01', 'cost_rate' => 50, 'sales_rate' => 100]);
        $this->actingAs($admin)->post(route('employees.rates.store', $employee), ['effective_from' => '2999-01-01', 'cost_rate' => 60, 'sales_rate' => 120]);
        $employee->load('rates');

        $this->assertSame('100.00', $employee->rateOn(today())->sales_rate);
        $this->assertSame('120.00', $employee->rateOn(now()->setYear(3000))->sales_rate);
    }

    public function test_leave_request_is_approved_by_manager_and_booked_on_the_balance(): void
    {
        $managerUser = $this->userWith([]);
        $manager = Employee::factory()->create(['user_id' => $managerUser->id]);
        $employeeUser = $this->userWith([]);
        $employee = Employee::factory()->create(['user_id' => $employeeUser->id, 'manager_id' => $manager->id]);
        $this->weekdaySchedule($employee);
        $employee->ledgerEntries()->create(['kind' => 'vacation', 'reason' => 'allocation', 'hours' => 100, 'entry_date' => today()]);
        $vacation = LeaveType::where('name', 'Vakantie')->firstOrFail();

        $this->actingAs($employeeUser)->post(route('employees.leave.store', $employee), [
            'leave_type_id' => $vacation->id, 'start_date' => '2030-07-01', 'end_date' => '2030-07-05',
        ])->assertSessionHasNoErrors();

        $request = LeaveRequest::firstOrFail();
        $this->assertSame('40.00', $request->hours);
        $this->assertSame('pending', $request->status->value);
        $this->assertSame(100.0, $employee->balance(BalanceKind::Vacation));

        $this->actingAs($employeeUser)->patch(route('leave-requests.decide', $request), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($managerUser)->get(route('leave-requests.index'))->assertOk()->assertSee($employee->full_name);
        $this->actingAs($managerUser)->patch(route('leave-requests.decide', $request), ['decision' => 'approve'])->assertRedirect();

        $this->assertSame('approved', $request->fresh()->status->value);
        $this->assertSame(60.0, $employee->balance(BalanceKind::Vacation));

        $this->actingAs($employeeUser)->patch(route('leave-requests.cancel', $request))->assertRedirect();
        $this->assertSame(100.0, $employee->balance(BalanceKind::Vacation));
    }

    public function test_rejected_leave_does_not_touch_the_balance_and_overlaps_are_blocked(): void
    {
        $employeeUser = $this->userWith([]);
        $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);
        $this->weekdaySchedule($employee);
        $vacation = LeaveType::where('name', 'Vakantie')->firstOrFail();
        $payload = ['leave_type_id' => $vacation->id, 'start_date' => '2030-07-01', 'end_date' => '2030-07-02'];

        $this->actingAs($employeeUser)->post(route('employees.leave.store', $employee), $payload);
        $this->actingAs($employeeUser)->post(route('employees.leave.store', $employee), $payload)->assertSessionHasErrors('start_date');

        $this->actingAs($this->userWith([Permission::LeaveApprove]))->patch(route('leave-requests.decide', LeaveRequest::firstOrFail()), ['decision' => 'reject', 'decision_note' => 'Drukte']);

        $this->assertSame('rejected', LeaveRequest::firstOrFail()->status->value);
        $this->assertSame(0.0, $employee->balance(BalanceKind::Vacation));
    }

    public function test_time_for_time_extra_hours_and_conversion(): void
    {
        $employee = Employee::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('employees.balance.store', $employee), ['action' => 'extra_hours', 'hours' => 10, 'entry_date' => today()->format('Y-m-d')]);
        $this->actingAs($admin)->post(route('employees.balance.store', $employee), ['action' => 'conversion', 'hours' => 4, 'entry_date' => today()->format('Y-m-d')]);
        $this->actingAs($admin)->post(route('employees.balance.store', $employee), ['action' => 'extra_hours', 'hours' => -1, 'entry_date' => today()->format('Y-m-d')])->assertSessionHasErrors('hours');

        $this->assertSame(6.0, $employee->balance(BalanceKind::TimeForTime));
        $this->assertSame(4.0, $employee->balance(BalanceKind::Vacation));
    }

    public function test_collective_leave_books_hours_for_a_team_only(): void
    {
        $team = Team::factory()->create();
        $inTeam = Employee::factory()->create(['team_id' => $team->id]);
        $outside = Employee::factory()->create();
        $noSchedule = Employee::factory()->create(['team_id' => $team->id]);
        $this->weekdaySchedule($inTeam);
        $this->weekdaySchedule($outside);
        $type = LeaveType::where('name', 'Collectieve vrije dag')->firstOrFail();

        $this->actingAs($this->admin())->post(route('collective-leave.store'), [
            'name' => 'Kerst', 'leave_type_id' => $type->id, 'start_date' => '2030-12-23', 'end_date' => '2030-12-24', 'scope' => 'teams', 'team_ids' => [$team->id],
        ])->assertRedirect()->assertSessionHas('collective_skipped');

        $this->assertSame(1, LeaveRequest::count());
        $this->assertSame(-16.0, $inTeam->balance(BalanceKind::Vacation));
        $this->assertSame(0.0, $outside->balance(BalanceKind::Vacation));
        $this->assertSame(0.0, $noSchedule->balance(BalanceKind::Vacation));
    }

    public function test_absence_can_be_registered_and_closed_without_overlap(): void
    {
        $employee = Employee::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('absences.store'), ['employee_id' => $employee->id, 'type' => 'sick', 'start_date' => '2030-01-02', 'percentage' => 100])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('absences.store'), ['employee_id' => $employee->id, 'type' => 'sick', 'start_date' => '2030-01-10', 'percentage' => 50])->assertSessionHasErrors('start_date');

        $absence = Absence::firstOrFail();
        $this->assertTrue($absence->isOngoing());

        $this->actingAs($admin)->put(route('absences.update', $absence), ['end_date' => '2030-01-09'])->assertRedirect();
        $this->assertFalse($absence->fresh()->isOngoing());
        $this->actingAs($this->userWith([Permission::EmployeesView]))->get(route('absences.index'))->assertForbidden();
    }

    public function test_documents_are_stored_privately_and_respect_permissions(): void
    {
        Storage::fake('local');
        $employee = Employee::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('employees.documents.store', $employee), [
            'category' => 'contract', 'title' => 'Arbeidscontract', 'file' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('employees.documents.store', $employee), [
            'category' => 'other', 'title' => 'Script', 'file' => UploadedFile::fake()->create('script.php', 1),
        ])->assertSessionHasErrors('file');

        $document = $employee->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->path);

        $this->actingAs($admin)->get(route('employees.documents.show', [$employee, $document]))->assertOk();
        $this->actingAs($this->userWith([Permission::EmployeesView]))->get(route('employees.documents.show', [$employee, $document]))->assertForbidden();

        $this->actingAs($admin)->delete(route('employees.documents.destroy', [$employee, $document]));
        Storage::disk('local')->assertMissing($document->path);
    }

    public function test_timeline_notes_are_limited_to_hr_and_managers(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin())->post(route('employees.notes.store', $employee), ['type' => 'phone', 'body' => 'Gebeld over rooster'])->assertRedirect();
        $this->assertSame('phone', $employee->notes()->firstOrFail()->type);
        $this->actingAs($this->userWith([Permission::EmployeesView]))->post(route('employees.notes.store', $employee), ['type' => 'note', 'body' => 'x'])->assertForbidden();
    }

    public function test_offboarding_ends_contracts_and_blocks_login(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id, 'hired_at' => '2020-01-01']);
        $employee->contracts()->create(['contract_type' => 'indefinite', 'employment_type' => 'full_time', 'start_date' => '2020-01-01']);

        $this->actingAs($this->admin())->post(route('employees.offboard', $employee), ['left_at' => today()->format('Y-m-d'), 'leave_reason' => 'Nieuwe baan'])->assertRedirect();

        $employee->refresh();
        $this->assertFalse($employee->is_active);
        $this->assertSame(today()->format('Y-m-d'), $employee->contracts()->first()->end_date->format('Y-m-d'));
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($this->admin())->patch(route('employees.reactivate', $employee));
        $this->assertTrue($employee->fresh()->is_active);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_employees_can_be_exported_and_imported(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['first_name' => 'Jan', 'last_name' => 'Jansen', 'email' => 'jan@bedrijf.nl']);

        $this->assertStringContainsString('jan@bedrijf.nl', $this->actingAs($admin)->get(route('employees.export'))->streamedContent());
        $this->assertStringContainsString('employee_number', $this->actingAs($admin)->get(route('employees.import.template'))->streamedContent());

        $csv = "first_name;last_name;email;team;manager_email;hired_at;contract_type;employment_type;hours_per_week\n"
            ."Piet;Peters;piet@bedrijf.nl;Sales;jan@bedrijf.nl;2024-02-01;indefinite;part_time;32\n"
            ."Jan;Dubbel;jan@bedrijf.nl;;;;;;\n"
            .";Fout;kapot;;;;;;\n";
        $file = UploadedFile::fake()->createWithContent('medewerkers.csv', $csv);

        $this->actingAs($admin)->post(route('employees.import.store'), ['file' => $file])->assertRedirect(route('employees.import'))
            ->assertSessionHas('import_result', fn (array $result) => $result['created'] === 1 && count($result['skipped']) === 1 && count($result['errors']) === 1);

        $piet = Employee::where('email', 'piet@bedrijf.nl')->firstOrFail();
        $this->assertSame('Sales', $piet->team->name);
        $this->assertSame('jan@bedrijf.nl', $piet->manager->email);
        $this->assertSame('part_time', $piet->currentContract->employment_type->value);
    }

    public function test_leave_and_absence_report_calculates_percentages(): void
    {
        $employee = Employee::factory()->create(['hired_at' => '2029-01-01']);
        $this->weekdaySchedule($employee);
        $employee->absences()->create(['type' => 'sick', 'start_date' => '2029-03-05', 'end_date' => '2029-03-06', 'percentage' => 50]);
        $employee->ledgerEntries()->create(['kind' => 'vacation', 'reason' => 'allocation', 'hours' => 200, 'entry_date' => '2029-01-01']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('reports.leave', ['year' => 2029]))->assertOk()->assertSee($employee->full_name)->assertSee('200');
        $this->assertStringContainsString($employee->full_name, $this->actingAs($admin)->get(route('reports.leave.export', ['year' => 2029]))->streamedContent());
        $this->actingAs($this->userWith([Permission::EmployeesView]))->get(route('reports.leave'))->assertForbidden();
    }

    public function test_groups_control_rights_and_the_last_administrator_is_protected(): void
    {
        $admin = $this->admin();
        $group = $admin->group;

        $this->actingAs($admin)->post(route('user-groups.store'), ['name' => 'Planners', 'permissions' => [Permission::EmployeesView->value]])->assertSessionHasNoErrors();
        $this->assertTrue(UserGroup::where('name', 'Planners')->firstOrFail()->grants(Permission::EmployeesView));

        $this->actingAs($admin)->put(route('user-groups.update', $group), ['name' => 'Beheerder', 'permissions' => [Permission::EmployeesView->value]])->assertSessionHasErrors('group');
        $this->actingAs($admin)->patch(route('users.group', $admin), ['user_group_id' => null])->assertSessionHasErrors('group');
        $this->actingAs($admin)->delete(route('user-groups.destroy', $group))->assertSessionHasErrors('group');
        $this->actingAs($this->userWith([Permission::EmployeesEdit]))->get(route('user-groups.index'))->assertForbidden();
    }

    public function test_pages_render(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create(['team_id' => Team::factory()->create()->id]);

        foreach ([route('employees.index'), route('employees.create'), route('employees.edit', $employee), route('employees.show', $employee),
            route('employees.import'), route('leave-requests.index'), route('collective-leave.index'), route('absences.index'),
            route('reports.leave'), route('teams.index'), route('disciplines.index'), route('user-groups.index')] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
