<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    private function isSelf(User $user, Employee $employee): bool
    {
        return $employee->user_id !== null && $employee->user_id === $user->id;
    }

    private function managesEmployee(User $user, Employee $employee): bool
    {
        return $employee->manager?->user_id !== null && $employee->manager->user_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::EmployeesView);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->isSelf($user, $employee) || $user->hasPermission(Permission::EmployeesView) || $this->managesEmployee($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::EmployeesEdit);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermission(Permission::EmployeesEdit);
    }

    public function viewPrivate(User $user, Employee $employee): bool
    {
        return $this->isSelf($user, $employee) || $user->hasPermission(Permission::EmployeesViewPrivate);
    }

    public function updatePrivate(User $user, Employee $employee): bool
    {
        return $user->hasPermission(Permission::EmployeesEditPrivate);
    }

    public function viewContracts(User $user, Employee $employee): bool
    {
        return $this->isSelf($user, $employee) || $user->hasPermission(Permission::ContractsView);
    }

    public function manageContracts(User $user, Employee $employee): bool
    {
        return $user->hasPermission(Permission::ContractsEdit);
    }

    public function viewDocuments(User $user, Employee $employee): bool
    {
        return $this->isSelf($user, $employee) || $user->hasPermission(Permission::DocumentsView);
    }

    public function manageDocuments(User $user, Employee $employee): bool
    {
        return $user->hasPermission(Permission::DocumentsEdit);
    }

    public function viewLeave(User $user, Employee $employee): bool
    {
        return $this->isSelf($user, $employee) || $this->managesEmployee($user, $employee)
            || $user->hasPermission(Permission::LeaveManage) || $user->hasPermission(Permission::LeaveApprove);
    }

    public function requestLeave(User $user, Employee $employee): bool
    {
        return $this->isSelf($user, $employee) || $user->hasPermission(Permission::LeaveManage);
    }

    public function approveLeave(User $user, Employee $employee): bool
    {
        if ($this->isSelf($user, $employee)) {
            return false;
        }

        return $user->hasPermission(Permission::LeaveApprove) || $this->managesEmployee($user, $employee);
    }

    public function manageLeave(User $user, Employee $employee): bool
    {
        return $user->hasPermission(Permission::LeaveManage);
    }

    public function viewAbsence(User $user, Employee $employee): bool
    {
        return $this->isSelf($user, $employee) || $user->hasPermission(Permission::AbsenceManage);
    }

    public function manageAbsence(User $user, Employee $employee): bool
    {
        return $user->hasPermission(Permission::AbsenceManage);
    }

    public function addNote(User $user, Employee $employee): bool
    {
        return $user->hasPermission(Permission::EmployeesEdit) || $this->managesEmployee($user, $employee);
    }

    public function viewNotes(User $user, Employee $employee): bool
    {
        return $user->hasPermission(Permission::EmployeesEdit) || $this->managesEmployee($user, $employee);
    }
}
