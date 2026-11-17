<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Administrators may view and manage invoices; HR and team managers may only view them.
     */
    public function up(): void
    {
        $grants = ['Beheerder' => ['invoices.view', 'invoices.manage'], 'HR' => ['invoices.view'], 'Teammanager' => ['invoices.view']];

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
        foreach (DB::table('user_groups')->get() as $group) {
            $current = array_diff(json_decode($group->permissions, true) ?? [], ['invoices.view', 'invoices.manage']);
            DB::table('user_groups')->where('id', $group->id)->update(['permissions' => json_encode(array_values($current))]);
        }
    }
};
