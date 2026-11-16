<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_normalized')->unique();
            $table->string('type')->default('customer')->index();
            $table->foreignId('industry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_size_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('relation_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('coc_number')->nullable()->unique();
            $table->string('vat_number')->nullable()->unique();

            $table->string('visit_street')->nullable();
            $table->string('visit_zipcode')->nullable();
            $table->string('visit_city')->nullable();
            $table->string('visit_country')->nullable();
            $table->string('postal_street')->nullable();
            $table->string('postal_zipcode')->nullable();
            $table->string('postal_city')->nullable();
            $table->string('postal_country')->nullable();

            $table->string('billing_name')->nullable();
            $table->string('billing_attention')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_street')->nullable();
            $table->string('billing_zipcode')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_country')->nullable();
            $table->string('iban')->nullable();
            $table->string('bic')->nullable();
            $table->string('debtor_number')->nullable();
            $table->string('invoice_reference')->nullable();
            $table->unsignedSmallInteger('payment_term_days')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
