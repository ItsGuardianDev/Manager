<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->nullable()->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credit_for_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('subject')->nullable();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_method', 20)->nullable();
            $table->boolean('reminder_paused')->default(false);
            $table->unsignedTinyInteger('reminders_sent')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('custom');
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(21);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->timestamps();
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('written_off_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn('written_off_at');
        });

        Schema::dropIfExists('invoice_events');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
