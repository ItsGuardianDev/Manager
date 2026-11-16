<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmployeeAccessController extends Controller
{
    public function invite(Request $request, Employee $employee, InvitationService $invitations): RedirectResponse
    {
        Gate::authorize('access.manage');

        $data = $request->validate(['user_group_id' => ['nullable', 'exists:user_groups,id']]);

        $invitations->invite($employee, $data['user_group_id'] ?? null);

        return redirect()->route('employees.show', [$employee, 'tab' => 'access'])
            ->with('status', 'Uitnodiging verstuurd naar '.$employee->email.'.');
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('access.manage');

        $data = $request->validate([
            'user_group_id' => ['nullable', 'exists:user_groups,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        abort_unless($employee->user, 404);

        $employee->user->update($data);

        return redirect()->route('employees.show', [$employee, 'tab' => 'access'])->with('status', 'Toegang bijgewerkt.');
    }
}
