<?php

namespace Tests\Feature;

use App\Enums\BalanceKind;
use App\Enums\Permission;
use App\Models\Employee;
use App\Models\HourType;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserGroup;
use App\Notifications\BudgetExceeded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoursModuleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['user_group_id' => UserGroup::where('name', 'Beheerder')->value('id')]);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    /**
     * @param  array<int, Permission>  $permissions
     */
    private function userWith(array $permissions): User
    {
        $user = User::factory()->create(['user_group_id' => UserGroup::factory()->with($permissions)->create()->id]);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    private function project(?Employee $manager = null): ProjectService
    {
        $project = Project::factory()->create(['organization_id' => Organization::factory()->create()->id, 'manager_id' => $manager?->id]);
        $service = $project->services()->create(['name' => 'Ontwerp', 'invoice_method' => 'after_calculation', 'sort_order' => 1]);
        $service->hourLines()->create(['hour_type_id' => HourType::where('name', 'Architect')->value('id'), 'hours' => 10, 'rate' => 100]);

        return $service;
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(ProjectService $service, array $overrides = []): array
    {
        return ['project_id' => $service->project_id, 'project_service_id' => $service->id, 'hour_type_id' => HourType::where('name', 'Architect')->value('id'), 'date' => today()->format('Y-m-d'), 'hours' => 2, ...$overrides];
    }

    private function weekdaySchedule(Employee $employee, string $end = '17:00'): void
    {
        $employee->schedules()->create(['effective_from' => '2020-01-01', 'alternating' => false, 'pattern' => ['odd' => array_fill_keys(range(1, 5), ['start' => '09:00', 'end' => $end, 'break' => 0])]]);
    }

    public function test_hours_module_requires_login_and_a_permission(): void
    {
        $this->get(route('hours.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('hours.index'))->assertForbidden();
        $this->actingAs($this->userWith([Permission::ProjectsView]))->get(route('hours.week'))->assertForbidden();
        $this->actingAs($this->userWith([Permission::HoursRegister]))->get(route('hours.index'))->assertOk();
    }

    public function test_default_groups_get_the_hours_permissions(): void
    {
        $this->assertContains('hours.manage', UserGroup::where('name', 'Beheerder')->firstOrFail()->permissions);
        $this->assertContains('hours.approve', UserGroup::where('name', 'Teammanager')->firstOrFail()->permissions);
        $this->assertContains('hours.register', UserGroup::where('name', 'Medewerker')->firstOrFail()->permissions);
        $this->assertNotContains('hours.approve', UserGroup::where('name', 'Medewerker')->firstOrFail()->permissions);
    }

    public function test_employee_registers_hours_with_times_and_budget_follows(): void
    {
        $user = $this->userWith([Permission::HoursRegister]);
        $service = $this->project();

        $this->actingAs($user)->post(route('hours.store'), $this->entry($service, ['hours' => '', 'start_time' => '09:00', 'end_time' => '12:30', 'note' => 'Schetsen']))->assertSessionHasNoErrors();

        $entry = TimeEntry::firstOrFail();
        $this->assertSame($user->employee->id, $entry->employee_id);
        $this->assertSame(3.5, $entry->hours, 'Hours follow from start and end time.');
        $this->assertSame('draft', $entry->status->value);
        $this->assertSame(100.0, $entry->rate, 'The rate of the service is used.');

        $this->actingAs($user)->post(route('hours.store'), $this->entry($service, ['hours' => 2, 'start_time' => '13:00']))->assertSessionHasNoErrors();
        $this->assertSame('15:00', TimeEntry::latest('id')->firstOrFail()->end_time, 'End time follows from start time and hours.');

        $this->assertSame(5.5, $service->project->fresh()->load(Project::BUDGET_RELATIONS)->spentHours());
    }

    public function test_registration_rules(): void
    {
        $user = $this->userWith([Permission::HoursRegister]);
        $service = $this->project();
        $other = Employee::factory()->create();

        $this->actingAs($user)->post(route('hours.store'), $this->entry($service, ['hours' => '']))->assertSessionHasErrors('hours');
        $this->actingAs($user)->post(route('hours.store'), $this->entry($service, ['start_time' => '12:00', 'end_time' => '10:00', 'hours' => '']))->assertSessionHasErrors('end_time');
        $this->actingAs($user)->post(route('hours.store'), $this->entry($service, ['hours' => 25]))->assertSessionHasErrors('hours');

        $this->actingAs($user)->post(route('hours.store'), $this->entry($service, ['hours' => 20]))->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('hours.store'), $this->entry($service, ['hours' => 5]))->assertSessionHasErrors('hours');

        $this->actingAs($user)->post(route('hours.store'), $this->entry($service, ['employee_id' => $other->id, 'date' => today()->subDay()->format('Y-m-d')]));
        $this->assertSame(0, TimeEntry::where('employee_id', $other->id)->count(), 'Employees cannot register for others.');
        $this->assertSame(1, TimeEntry::where('employee_id', $user->employee->id)->whereDate('date', today()->subDay())->count(), 'The hours land on the employee themselves.');

        $service->update(['allow_hours' => false]);
        $this->actingAs($user)->post(route('hours.store'), $this->entry($service, ['date' => today()->subDays(2)->format('Y-m-d')]))->assertSessionHasErrors('project_service_id');

        $service->update(['allow_hours' => true]);
        $service->project->update(['status' => 'closed']);
        $this->actingAs($user)->post(route('hours.store'), $this->entry($service, ['date' => today()->subDays(2)->format('Y-m-d')]))->assertSessionHasErrors('hours');
        $this->assertSame(2, TimeEntry::count());
    }

    public function test_hours_can_be_registered_for_earlier_dates_and_on_internal_projects(): void
    {
        $user = $this->userWith([Permission::HoursRegister]);
        $service = $this->project();
        $service->project->update(['organization_id' => null, 'is_invoiceable' => false]);

        $this->actingAs($user)->post(route('hours.store'), $this->entry($service, ['date' => today()->subMonths(2)->format('Y-m-d')]))->assertSessionHasNoErrors();
        $this->assertSame(1, TimeEntry::count());
    }

    public function test_managers_register_and_correct_hours_for_others(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create();
        $service = $this->project();

        $this->actingAs($admin)->post(route('hours.store'), $this->entry($service, ['employee_id' => $employee->id]))->assertSessionHasNoErrors();
        $this->assertSame($employee->id, TimeEntry::firstOrFail()->employee_id);
    }

    public function test_employees_see_and_change_only_their_own_open_hours(): void
    {
        $user = $this->userWith([Permission::HoursRegister]);
        $colleague = $this->userWith([Permission::HoursRegister]);
        $service = $this->project();

        $mine = TimeEntry::create([...$this->entry($service), 'employee_id' => $user->employee->id, 'rate' => 100, 'status' => 'draft', 'note' => 'Mijn uren']);
        $theirs = TimeEntry::create([...$this->entry($service), 'employee_id' => $colleague->employee->id, 'rate' => 100, 'status' => 'draft', 'note' => 'Hun uren']);

        $this->actingAs($user)->get(route('hours.index'))->assertSee('Mijn uren')->assertDontSee('Hun uren');
        $this->actingAs($user)->put(route('hours.update', $theirs), $this->entry($service))->assertForbidden();
        $this->actingAs($user)->delete(route('hours.destroy', $theirs))->assertForbidden();

        $this->actingAs($user)->put(route('hours.update', $mine), $this->entry($service, ['hours' => 4]))->assertSessionHasNoErrors();
        $this->assertSame(4.0, $mine->fresh()->hours);

        $mine->update(['status' => 'submitted']);
        $this->actingAs($user)->put(route('hours.update', $mine), $this->entry($service, ['hours' => 5]))->assertForbidden();
        $this->actingAs($user)->delete(route('hours.destroy', $mine))->assertForbidden();
    }

    public function test_hours_are_submitted_approved_or_rejected_and_resubmitted(): void
    {
        $user = $this->userWith([Permission::HoursRegister]);
        $approver = $this->userWith([Permission::HoursRegister, Permission::HoursApprove]);
        $service = $this->project();
        $entry = TimeEntry::create([...$this->entry($service), 'employee_id' => $user->employee->id, 'rate' => 100, 'status' => 'draft']);

        $this->actingAs($user)->post(route('hours.bulk'), ['ids' => [$entry->id], 'action' => 'approve'])->assertSessionHas('status', fn ($s) => str_contains($s, 'niets'));
        $this->assertSame('draft', $entry->fresh()->status->value);

        $this->actingAs($user)->post(route('hours.bulk'), ['ids' => [$entry->id], 'action' => 'submit']);
        $this->assertSame('submitted', $entry->fresh()->status->value);

        $this->actingAs($user)->post(route('hours.bulk'), ['ids' => [$entry->id], 'action' => 'approve']);
        $this->assertSame('submitted', $entry->fresh()->status->value, 'Employees cannot approve their own hours.');

        $this->actingAs($approver)->get(route('hours.approvals'))->assertOk();
        $this->actingAs($approver)->post(route('hours.bulk'), ['ids' => [$entry->id], 'action' => 'reject'])->assertSessionHasErrors('reason');
        $this->actingAs($approver)->post(route('hours.bulk'), ['ids' => [$entry->id], 'action' => 'reject', 'reason' => 'Verkeerde dienst']);
        $entry->refresh();
        $this->assertSame('rejected', $entry->status->value);
        $this->assertSame('Verkeerde dienst', $entry->reject_reason);

        $this->actingAs($user)->put(route('hours.update', $entry), $this->entry($service, ['hours' => 3]))->assertSessionHasNoErrors();
        $this->assertSame('draft', $entry->fresh()->status->value, 'Editing rejected hours makes them a concept again.');

        $this->actingAs($user)->post(route('hours.bulk'), ['ids' => [$entry->id], 'action' => 'submit']);
        $this->actingAs($approver)->post(route('hours.bulk'), ['ids' => [$entry->id], 'action' => 'approve']);
        $entry->refresh();
        $this->assertSame('approved', $entry->status->value);
        $this->assertSame($approver->id, $entry->reviewed_by);
    }

    public function test_project_managers_approve_hours_on_their_projects(): void
    {
        $user = $this->userWith([Permission::HoursRegister]);
        $projectManager = $this->userWith([Permission::HoursRegister]);
        $service = $this->project($projectManager->employee);
        $other = $this->project();
        $mine = TimeEntry::create([...$this->entry($service), 'employee_id' => $user->employee->id, 'rate' => 100, 'status' => 'submitted', 'note' => 'Op mijn project']);
        $elsewhere = TimeEntry::create([...$this->entry($other), 'employee_id' => $user->employee->id, 'rate' => 100, 'status' => 'submitted', 'note' => 'Op een ander project']);

        $this->actingAs($projectManager)->get(route('hours.approvals'))->assertOk()->assertSee('Op mijn project')->assertDontSee('Op een ander project');
        $this->actingAs($projectManager)->post(route('hours.bulk'), ['ids' => [$mine->id, $elsewhere->id], 'action' => 'approve']);

        $this->assertSame('approved', $mine->fresh()->status->value);
        $this->assertSame('submitted', $elsewhere->fresh()->status->value, 'Only hours on their own projects can be reviewed.');
    }

    public function test_approved_overtime_becomes_time_for_time_balance(): void
    {
        $manager = $this->admin();
        $user = $this->userWith([Permission::HoursRegister]);
        $this->weekdaySchedule($user->employee);
        $service = $this->project();
        $monday = today()->startOfWeek()->subWeek();
        $entry = TimeEntry::create([...$this->entry($service, ['date' => $monday->format('Y-m-d'), 'hours' => 10]), 'employee_id' => $user->employee->id, 'rate' => 100, 'status' => 'submitted']);

        $this->assertSame(0.0, $user->employee->balance(BalanceKind::TimeForTime));
        $this->actingAs($manager)->post(route('hours.bulk'), ['ids' => [$entry->id], 'action' => 'approve']);
        $this->assertSame(2.0, $user->employee->fresh()->balance(BalanceKind::TimeForTime), '10 approved hours on an 8 hour day is 2 hours overtime.');

        $this->actingAs($manager)->post(route('hours.bulk'), ['ids' => [$entry->id], 'action' => 'approve']);
        $this->assertSame(2.0, $user->employee->fresh()->balance(BalanceKind::TimeForTime), 'Approving twice does not double count.');

        $this->actingAs($manager)->post(route('hours.bulk'), ['ids' => [$entry->id], 'action' => 'reject', 'reason' => 'Fout']);
        $this->assertSame(0.0, $user->employee->fresh()->balance(BalanceKind::TimeForTime), 'Rejecting takes the overtime back.');
    }

    public function test_invoiced_hours_are_locked_until_a_manager_corrects_them_with_a_reason(): void
    {
        $manager = $this->admin();
        $user = $this->userWith([Permission::HoursRegister]);
        $service = $this->project();
        $entry = TimeEntry::create([...$this->entry($service), 'employee_id' => $user->employee->id, 'rate' => 100, 'status' => 'approved']);

        $this->actingAs($user)->post(route('hours.bulk'), ['ids' => [$entry->id], 'action' => 'invoice']);
        $this->assertFalse($entry->fresh()->isLocked(), 'Only managers can mark hours as processed.');

        $this->actingAs($manager)->post(route('hours.bulk'), ['ids' => [$entry->id], 'action' => 'invoice']);
        $this->assertTrue($entry->fresh()->isLocked());

        $this->actingAs($user)->put(route('hours.update', $entry), $this->entry($service, ['hours' => 1]))->assertForbidden();
        $this->actingAs($manager)->put(route('hours.update', $entry), $this->entry($service, ['hours' => 1, 'employee_id' => $user->employee->id]))->assertSessionHasErrors('correction_reason');
        $this->assertSame(2.0, $entry->fresh()->hours);

        $this->actingAs($manager)->put(route('hours.update', $entry), $this->entry($service, ['hours' => 1, 'employee_id' => $user->employee->id, 'correction_reason' => 'Verkeerd getypt']))->assertSessionHasNoErrors();
        $this->assertSame(1.0, $entry->fresh()->hours);
        $this->assertTrue($entry->fresh()->isLocked());
        $this->assertStringContainsString('Verkeerd getypt', $service->project->fresh()->notes()->latest('id')->firstOrFail()->body);

        $this->actingAs($manager)->delete(route('hours.destroy', $entry))->assertSessionHasErrors('correction_reason');
        $this->actingAs($manager)->delete(route('hours.destroy', $entry), ['correction_reason' => 'Dubbel']);
        $this->assertNull(TimeEntry::find($entry->id));
    }

    public function test_hours_can_be_moved_duplicated_and_split(): void
    {
        $user = $this->userWith([Permission::HoursRegister]);
        $service = $this->project();
        $second = $service->project->services()->create(['name' => 'Uitvoering', 'invoice_method' => 'after_calculation', 'sort_order' => 2]);
        $entry = TimeEntry::create([...$this->entry($service, ['hours' => 6, 'start_time' => '09:00', 'end_time' => '15:00']), 'employee_id' => $user->employee->id, 'rate' => 100, 'status' => 'draft']);

        $this->actingAs($user)->put(route('hours.update', $entry), $this->entry($second, ['hours' => 6, 'date' => today()->subDay()->format('Y-m-d')]))->assertSessionHasNoErrors();
        $entry->refresh();
        $this->assertSame($second->id, $entry->project_service_id, 'Hours can be moved to another service.');
        $this->assertSame(today()->subDay()->format('Y-m-d'), $entry->date->format('Y-m-d'));

        $this->actingAs($user)->post(route('hours.duplicate', $entry), ['date' => today()->subDays(3)->format('Y-m-d')])->assertSessionHasNoErrors();
        $this->assertSame(2, TimeEntry::count());
        $this->assertSame(6.0, TimeEntry::latest('id')->firstOrFail()->hours);

        $this->actingAs($user)->post(route('hours.split', $entry), ['split_hours' => 6, 'project_service_id' => $service->id])->assertSessionHasErrors('split_hours');
        $this->actingAs($user)->post(route('hours.split', $entry), ['split_hours' => 2.5, 'project_service_id' => $service->id, 'hour_type_id' => HourType::where('name', 'Tekenaar')->value('id')])->assertSessionHasNoErrors();

        $this->assertSame(3, TimeEntry::count());
        $this->assertSame(3.5, $entry->fresh()->hours);
        $part = TimeEntry::latest('id')->firstOrFail();
        $this->assertSame([2.5, $service->id], [$part->hours, $part->project_service_id]);
        $this->assertSame(12.0, TimeEntry::sum('hours'), 'Splitting keeps the total (6 + 6 duplicated).');
    }

    public function test_timesheet_saves_cells_submits_and_copies_weeks(): void
    {
        $user = $this->userWith([Permission::HoursRegister]);
        $service = $this->project();
        $monday = today()->startOfWeek();
        $row = fn (array $hours) => ['project_id' => $service->project_id, 'project_service_id' => $service->id, 'hour_type_id' => HourType::where('name', 'Architect')->value('id'), 'hours' => $hours];
        $key = fn (int $day) => $monday->copy()->addDays($day)->format('Y-m-d');

        $this->actingAs($user)->post(route('hours.week.save'), ['date' => $monday->format('Y-m-d'), 'rows' => [$row([$key(0) => '8', $key(1) => '4.5', $key(2) => ''])]])->assertSessionHasNoErrors();
        $this->assertSame([8.0, 4.5], TimeEntry::orderBy('date')->pluck('hours')->all());

        $this->actingAs($user)->post(route('hours.week.save'), ['date' => $monday->format('Y-m-d'), 'rows' => [$row([$key(0) => '7', $key(1) => '', $key(3) => '2'])]]);
        $this->assertSame([7.0, 2.0], TimeEntry::orderBy('date')->pluck('hours')->all(), 'A changed cell updates, an emptied cell deletes, a new cell creates.');

        $this->actingAs($user)->post(route('hours.week.save'), ['date' => $monday->format('Y-m-d'), 'rows' => [$row([$key(0) => '25'])]])->assertSessionHasErrors();
        $this->assertSame(7.0, TimeEntry::orderBy('date')->firstOrFail()->hours);

        $this->actingAs($user)->post(route('hours.week.submit'), ['date' => $monday->format('Y-m-d')]);
        $this->assertSame(['submitted'], TimeEntry::pluck('status')->map(fn ($s) => $s->value)->unique()->all());

        $this->actingAs($user)->post(route('hours.week.save'), ['date' => $monday->format('Y-m-d'), 'rows' => [$row([$key(0) => '1'])]]);
        $this->assertSame(7.0, TimeEntry::orderBy('date')->firstOrFail()->hours, 'Submitted hours can no longer be changed from the timesheet.');

        $next = $monday->copy()->addWeek();
        $this->actingAs($user)->post(route('hours.week.copy'), ['date' => $monday->format('Y-m-d'), 'target' => $next->format('Y-m-d')])->assertSessionHasNoErrors();
        $this->assertSame(4, TimeEntry::count());
        $this->assertSame(2, TimeEntry::whereDate('date', '>=', $next)->where('status', 'draft')->count(), 'Copied hours start as concept.');

        $this->actingAs($user)->post(route('hours.week.copy'), ['date' => $monday->format('Y-m-d'), 'target' => $next->format('Y-m-d')]);
        $this->assertSame(4, TimeEntry::count(), 'Copying twice does not duplicate.');
    }

    public function test_over_budget_hours_notify_the_project_manager(): void
    {
        $manager = $this->userWith([Permission::HoursRegister]);
        $user = $this->userWith([Permission::HoursRegister]);
        $service = $this->project($manager->employee);

        $this->actingAs($user)->post(route('hours.store'), $this->entry($service, ['hours' => 11]))->assertSessionHasNoErrors();

        $this->assertCount(1, $manager->notifications);
        $this->assertSame(BudgetExceeded::class, $manager->notifications->first()->type);
    }

    public function test_reports_and_exports_respect_filters_and_visibility(): void
    {
        $admin = $this->admin();
        $user = $this->userWith([Permission::HoursRegister]);
        $service = $this->project();
        $this->weekdaySchedule($user->employee);
        TimeEntry::create([...$this->entry($service, ['hours' => 5]), 'employee_id' => $user->employee->id, 'rate' => 100, 'status' => 'approved', 'note' => 'Mijn rapportuur']);
        TimeEntry::create([...$this->entry($service, ['hours' => 3]), 'employee_id' => $admin->employee->id, 'rate' => 100, 'status' => 'draft', 'note' => 'Beheerder uur']);

        $filters = ['from' => today()->startOfMonth()->format('Y-m-d'), 'to' => today()->endOfMonth()->format('Y-m-d')];
        $this->actingAs($admin)->get(route('hours.report', $filters))->assertOk()->assertSee($service->project->name)->assertSee('8');
        $this->actingAs($admin)->get(route('hours.report', [...$filters, 'employee_id' => $user->employee->id]))->assertOk();
        $this->actingAs($user)->get(route('hours.report', $filters))->assertOk()->assertDontSee($admin->employee->full_name);

        $csv = $this->actingAs($user)->get(route('hours.report.export', [...$filters, 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('Mijn rapportuur', $csv);
        $this->assertStringNotContainsString('Beheerder uur', $csv, 'Employees only export their own hours.');

        $workbook = $this->actingAs($admin)->get(route('hours.report.export', [...$filters, 'status' => 'approved']))->assertOk();
        $zip = new \ZipArchive;
        $zip->open($workbook->baseResponse->getFile()->getPathname());
        preg_match_all('/<sheet name="([^"]+)"/', $zip->getFromName('xl/workbook.xml'), $matches);
        $this->assertSame(['Uren', 'Per medewerker', 'Per project', 'Per dienst', 'Per urensoort'], $matches[1]);
        $this->assertStringContainsString('Mijn rapportuur', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringNotContainsString('Beheerder uur', $zip->getFromName('xl/worksheets/sheet1.xml'));
    }

    public function test_hours_pages_render(): void
    {
        $admin = $this->admin();
        $service = $this->project($admin->employee);
        $this->weekdaySchedule($admin->employee);
        TimeEntry::create([...$this->entry($service, ['hours' => 3, 'start_time' => '09:00', 'end_time' => '12:00']), 'employee_id' => $admin->employee->id, 'rate' => 100, 'status' => 'submitted', 'note' => 'Zichtbaar']);
        TimeEntry::create([...$this->entry($service, ['hours' => 1]), 'employee_id' => $admin->employee->id, 'rate' => 100, 'status' => 'draft']);

        $this->actingAs($admin)->get(route('hours.index'))->assertOk()->assertSee('Zichtbaar')->assertSee('Uren registreren');
        $this->actingAs($admin)->get(route('hours.index', ['status' => 'draft', 'project_id' => $service->project_id]))->assertOk()->assertDontSee('Zichtbaar');
        $this->actingAs($admin)->get(route('hours.approvals'))->assertOk()->assertSee('Goedkeuren');
        $this->actingAs($admin)->get(route('hours.week'))->assertOk()->assertSee('Weekstaat')->assertSee('Rooster')->assertSee($service->project->name);
        $this->actingAs($admin)->get(route('hours.calendar'))->assertOk()->assertSee($service->project->name);
        $this->actingAs($admin)->get(route('hours.calendar', ['view' => 'day', 'date' => today()->format('Y-m-d')]))->assertOk()->assertSee('09:00');
        $this->actingAs($admin)->get(route('hours.report'))->assertOk()->assertSee('Per medewerker');
        $this->actingAs($admin)->get(route('projects.show', [$service->project, 'tab' => 'hours']))->assertOk()->assertSee('Ingediend');
    }
}
