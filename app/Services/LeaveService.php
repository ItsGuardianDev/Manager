<?php

namespace App\Services;

use App\Enums\BalanceKind;
use App\Enums\LeaveStatus;
use App\Enums\LedgerReason;
use App\Models\CollectiveLeave;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    public function overlaps(Employee $employee, Carbon $from, Carbon $to, ?int $exceptId = null): bool
    {
        return $employee->leaveRequests()
            ->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value])
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    public function request(Employee $employee, LeaveType $type, Carbon $from, Carbon $to, float $hours, ?string $note): LeaveRequest
    {
        return $employee->leaveRequests()->create([
            'leave_type_id' => $type->id,
            'start_date' => $from,
            'end_date' => $to,
            'hours' => $hours,
            'note' => $note,
            'status' => LeaveStatus::Pending,
        ]);
    }

    public function approve(LeaveRequest $request, User $by, ?string $note = null): void
    {
        DB::transaction(function () use ($request, $by, $note): void {
            $request->update([
                'status' => LeaveStatus::Approved,
                'decided_by' => $by->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            $this->book($request, $by);
        });
    }

    public function reject(LeaveRequest $request, User $by, ?string $note = null): void
    {
        $request->update([
            'status' => LeaveStatus::Rejected,
            'decided_by' => $by->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
    }

    /**
     * Withdraw a request; approved hours are returned to the balance.
     */
    public function cancel(LeaveRequest $request): void
    {
        DB::transaction(function () use ($request): void {
            LeaveLedgerEntry::where('leave_request_id', $request->id)->delete();
            $request->update(['status' => LeaveStatus::Cancelled]);
        });
    }

    /**
     * Book approved leave for several employees at once, skipping people who are away already or have no scheduled hours.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array{created: int, skipped: array<int, string>}
     */
    public function createCollective(CollectiveLeave $collective, Collection $employees, User $by): array
    {
        $result = ['created' => 0, 'skipped' => []];

        DB::transaction(function () use ($collective, $employees, $by, &$result): void {
            foreach ($employees->load('schedules') as $employee) {
                $hours = $employee->scheduledHours($collective->start_date, $collective->end_date);

                if ($hours <= 0) {
                    $result['skipped'][] = "{$employee->full_name}: geen werkuren in deze periode";

                    continue;
                }

                if ($this->overlaps($employee, $collective->start_date, $collective->end_date)) {
                    $result['skipped'][] = "{$employee->full_name}: heeft al verlof in deze periode";

                    continue;
                }

                $request = $employee->leaveRequests()->create([
                    'leave_type_id' => $collective->leave_type_id,
                    'collective_leave_id' => $collective->id,
                    'start_date' => $collective->start_date,
                    'end_date' => $collective->end_date,
                    'hours' => $hours,
                    'status' => LeaveStatus::Approved,
                    'decided_by' => $by->id,
                    'decided_at' => now(),
                    'note' => $collective->name,
                ]);

                $this->book($request, $by);
                $result['created']++;
            }
        });

        return $result;
    }

    private function book(LeaveRequest $request, User $by): void
    {
        $kind = $request->leaveType->balance_kind;

        if ($kind === BalanceKind::None) {
            return;
        }

        LeaveLedgerEntry::create([
            'employee_id' => $request->employee_id,
            'kind' => $kind,
            'reason' => LedgerReason::Taken,
            'hours' => -1 * (float) $request->hours,
            'entry_date' => $request->start_date,
            'description' => $request->leaveType->name.' '.$request->start_date->format('d-m-Y').' t/m '.$request->end_date->format('d-m-Y'),
            'leave_request_id' => $request->id,
            'user_id' => $by->id,
        ]);
    }
}
