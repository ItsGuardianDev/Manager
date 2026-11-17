<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->string('start_time', 5)->nullable();
            $table->string('end_time', 5)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamp('invoiced_at')->nullable();

            $table->index(['employee_id', 'date']);
            $table->index('status');
        });

        Schema::table('leave_ledger_entries', function (Blueprint $table) {
            $table->date('hours_date')->nullable()->index();
        });

        // Hours that were registered before the approval flow existed count as approved.
        DB::table('time_entries')->update(['status' => 'approved']);

        $grants = [
            'Beheerder' => ['hours.register', 'hours.approve', 'hours.manage'],
            'HR' => ['hours.register'],
            'Teammanager' => ['hours.register', 'hours.approve'],
            'Medewerker' => ['hours.register'],
        ];

        foreach ($grants as $name => $permissions) {
            $group = DB::table('user_groups')->where('name', $name)->first();

            if ($group) {
                $current = json_decode($group->permissions, true) ?? [];
                DB::table('user_groups')->where('id', $group->id)->update(['permissions' => json_encode(array_values(array_unique([...$current, ...$permissions])))]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('leave_ledger_entries', function (Blueprint $table) {
            $table->dropIndex(['hours_date']);
            $table->dropColumn('hours_date');
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['employee_id', 'date']);
            $table->dropIndex(['status']);
            $table->dropColumn(['start_time', 'end_time', 'status', 'submitted_at', 'reviewed_by', 'reviewed_at', 'reject_reason', 'invoiced_at']);
        });

        foreach (DB::table('user_groups')->get() as $group) {
            $current = array_diff(json_decode($group->permissions, true) ?? [], ['hours.register', 'hours.approve', 'hours.manage']);
            DB::table('user_groups')->where('id', $group->id)->update(['permissions' => json_encode(array_values($current))]);
        }
    }
};
