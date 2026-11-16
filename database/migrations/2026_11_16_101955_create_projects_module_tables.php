<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hour_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('default_rate', 8, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('cost_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('default_margin', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('revenue_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('default_services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('invoice_method', 20)->nullable();
            $table->foreignId('revenue_group_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('name');
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->boolean('is_on_hold')->default(false);
            $table->boolean('is_invoiceable')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('track_kilometers')->default(true);
            $table->decimal('kilometer_price', 6, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('copied_from_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('employee_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unique(['project_id', 'employee_id']);
        });

        Schema::create('project_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unique(['project_id', 'team_id']);
        });

        Schema::create('project_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('default_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('invoice_method', 20);
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('revenue_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vat_code')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->date('invoice_from')->nullable();
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->unsignedTinyInteger('completion_percentage')->default(0);
            $table->timestamp('hours_alerted_at')->nullable();
            $table->timestamp('costs_alerted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('service_hour_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hour_type_id')->constrained();
            $table->decimal('hours', 8, 2);
            $table->decimal('rate', 8, 2);
        });

        Schema::create('service_cost_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_type_id')->constrained();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('purchase_price', 12, 2);
            $table->decimal('margin_percentage', 5, 2)->default(0);
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hour_type_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->decimal('hours', 6, 2);
            $table->decimal('rate', 8, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'date']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->date('date');
            $table->string('description');
            $table->string('source')->nullable();
            $table->string('type', 20)->default('invoice');
            $table->string('status', 20)->default('to_process');
            $table->timestamps();
        });

        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_price', 12, 2);
        });

        $now = now();
        foreach ([['Architect', 125], ['Tekenaar', 95], ['Bouwkundige', 100], ['Project manager', 95], ['Projectleider', 110], ['Ontwikkelaar', 100], ['Ontwerper', 90]] as [$name, $rate]) {
            DB::table('hour_types')->insert(['name' => $name, 'default_rate' => $rate, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([['Inhuur derden', 0], ['Materiaal', 15], ['Reiskosten', 0], ['Licenties', 10]] as [$name, $margin]) {
            DB::table('cost_types')->insert(['name' => $name, 'default_margin' => $margin, 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('revenue_groups')->insert(['name' => 'Algemeen', 'created_at' => $now, 'updated_at' => $now]);
        foreach (['Acquisitie', 'Projectdefinitie', 'Initiatief / haalbaarheid', 'Schetsontwerp', 'Voorontwerp', 'Definitief ontwerp', 'Technisch ontwerp', 'Uitvoering', 'Gebruik / Exploitatie', 'Meerwerk', 'Interne werkzaamheden'] as $name) {
            DB::table('default_services')->insert(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        foreach (['purchase_lines', 'purchases', 'time_entries', 'service_cost_lines', 'service_hour_lines', 'project_services', 'project_team', 'employee_project', 'projects', 'default_services', 'revenue_groups', 'cost_types', 'hour_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
