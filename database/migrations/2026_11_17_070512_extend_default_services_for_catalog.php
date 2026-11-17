<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('default_services', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->text('internal_note')->nullable();
            $table->string('vat_code')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->boolean('allow_hours')->default(true);
            $table->boolean('allow_costs')->default(true);
            $table->boolean('is_plannable')->default(true);
            $table->decimal('price', 12, 2)->nullable();
            $table->boolean('price_editable')->default(true);
            $table->string('billing_cycle', 20)->nullable();
        });

        Schema::table('project_services', function (Blueprint $table) {
            $table->boolean('allow_hours')->default(true);
            $table->boolean('allow_costs')->default(true);
            $table->boolean('is_plannable')->default(true);
            $table->string('billing_cycle', 20)->nullable();
        });

        Schema::create('default_service_hour_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('default_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hour_type_id')->constrained();
            $table->decimal('hours', 8, 2)->default(0);
            $table->decimal('rate', 8, 2);
        });

        Schema::create('default_service_cost_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('default_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_type_id')->constrained();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('purchase_price', 12, 2);
            $table->decimal('margin_percentage', 5, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('default_service_cost_lines');
        Schema::dropIfExists('default_service_hour_lines');
        Schema::table('project_services', fn (Blueprint $table) => $table->dropColumn(['allow_hours', 'allow_costs', 'is_plannable', 'billing_cycle']));
        Schema::table('default_services', fn (Blueprint $table) => $table->dropColumn(['description', 'internal_note', 'vat_code', 'is_blocked', 'allow_hours', 'allow_costs', 'is_plannable', 'price', 'price_editable', 'billing_cycle']));
    }
};
