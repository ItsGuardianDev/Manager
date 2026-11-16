<?php

use App\Enums\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $all = Permission::values();

        $groups = [
            'Beheerder' => ['Volledige toegang, inclusief rechtenbeheer.', $all],
            'HR' => ['Personeelszaken: medewerkers, contracten, verlof en verzuim.', array_values(array_diff($all, [Permission::AccessManage->value]))],
            'Teammanager' => ['Ziet basisgegevens van medewerkers; keurt verlof van eigen team goed.', [Permission::EmployeesView->value]],
            'Medewerker' => ['Ziet alleen de eigen gegevens en vraagt verlof aan.', []],
        ];

        foreach ($groups as $name => [$description, $permissions]) {
            DB::table('user_groups')->insert([
                'name' => $name, 'description' => $description, 'permissions' => json_encode($permissions),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $leaveTypes = [
            ['Vakantie', 'vacation'], ['Tijd-voor-tijd', 'tvt'], ['Collectieve vrije dag', 'vacation'],
            ['Bijzonder verlof', 'none'], ['Zorgverlof', 'none'], ['Onbetaald verlof', 'none'],
        ];

        foreach ($leaveTypes as [$name, $kind]) {
            DB::table('leave_types')->insert(['name' => $name, 'balance_kind' => $kind, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Everyone who could log in before module 2 keeps full access.
        DB::table('users')->update(['user_group_id' => DB::table('user_groups')->where('name', 'Beheerder')->value('id')]);
    }

    public function down(): void
    {
        DB::table('leave_types')->delete();
        DB::table('user_groups')->delete();
    }
};
