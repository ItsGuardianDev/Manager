<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->unique()->constrained('people')->nullOnDelete();
            $table->string('employee_type', 20)->default('own');
            $table->string('marital_status', 30)->nullable();
            $table->string('private_phone')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('photo_path')->nullable();
        });

        Schema::create('employee_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unique(['employee_id', 'team_id']);
        });

        Schema::create('discipline_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discipline_id')->constrained()->cascadeOnDelete();
            $table->unique(['employee_id', 'discipline_id']);
        });

        foreach (DB::table('employees')->whereNotNull('team_id')->get(['id', 'team_id']) as $row) {
            DB::table('employee_team')->insert(['employee_id' => $row->id, 'team_id' => $row->team_id]);
        }
        foreach (DB::table('employees')->whereNotNull('discipline_id')->get(['id', 'discipline_id']) as $row) {
            DB::table('discipline_employee')->insert(['employee_id' => $row->id, 'discipline_id' => $row->discipline_id]);
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropConstrainedForeignId('discipline_id');
        });

        Schema::table('work_schedules', function (Blueprint $table) {
            $table->decimal('cost_rate', 8, 2)->nullable();
            $table->decimal('sales_rate', 8, 2)->nullable();
        });

        foreach (DB::table('employee_rates')->orderBy('effective_from')->get() as $rate) {
            $schedule = DB::table('work_schedules')->where('employee_id', $rate->employee_id)
                ->where('effective_from', '<=', $rate->effective_from)->orderByDesc('effective_from')->first();

            if (! $schedule) {
                continue;
            }

            if ($schedule->effective_from === $rate->effective_from) {
                DB::table('work_schedules')->where('id', $schedule->id)->update(['cost_rate' => $rate->cost_rate, 'sales_rate' => $rate->sales_rate]);
            } else {
                DB::table('work_schedules')->insert([
                    'employee_id' => $rate->employee_id, 'effective_from' => $rate->effective_from, 'alternating' => $schedule->alternating,
                    'pattern' => $schedule->pattern, 'cost_rate' => $rate->cost_rate, 'sales_rate' => $rate->sales_rate,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::dropIfExists('employee_rates');
    }

    public function down(): void
    {
        Schema::table('work_schedules', fn (Blueprint $table) => $table->dropColumn(['cost_rate', 'sales_rate']));
        Schema::dropIfExists('discipline_employee');
        Schema::dropIfExists('employee_team');
    }
};
