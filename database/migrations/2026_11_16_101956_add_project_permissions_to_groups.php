<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Existing groups get the new project rights that match their role.
     */
    public function up(): void
    {
        $grants = ['Beheerder' => ['projects.view', 'projects.edit'], 'HR' => ['projects.view'], 'Teammanager' => ['projects.view']];

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
            $current = array_diff(json_decode($group->permissions, true) ?? [], ['projects.view', 'projects.edit']);
            DB::table('user_groups')->where('id', $group->id)->update(['permissions' => json_encode(array_values($current))]);
        }
    }
};
