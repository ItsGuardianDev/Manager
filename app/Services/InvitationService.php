<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\EmployeeInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitationService
{
    /**
     * Create (or refresh) the login for an employee and mail them an activation link.
     * Returns the activation URL so it can be shown when mail is not configured.
     */
    public function invite(Employee $employee, ?int $groupId = null): string
    {
        $token = Str::random(48);

        $user = DB::transaction(function () use ($employee, $groupId, $token): User {
            $user = $employee->user ?? User::firstOrNew(['email' => $employee->email]);

            $user->fill([
                'name' => $employee->full_name,
                'is_active' => true,
                'invitation_token' => hash('sha256', $token),
                'invited_at' => now(),
            ]);

            if (! $user->exists) {
                $user->password = Str::random(40);
            }

            if ($groupId !== null) {
                $user->user_group_id = $groupId;
            }

            $user->save();
            $employee->update(['user_id' => $user->id]);

            return $user;
        });

        $notification = new EmployeeInvitation($token);
        $user->notify($notification);

        return $notification->url($user);
    }
}
