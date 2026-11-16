<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_attention', 20)->default('none');
            $table->foreignId('invoice_contact_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('default_vat_code')->nullable();
            $table->boolean('is_vat_exempt')->default(false);
            $table->string('invoice_template')->nullable();
            $table->string('reminder_plan')->nullable();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->renameColumn('billing_email', 'reminder_email');
            $table->dropColumn([
                'billing_name', 'billing_attention', 'billing_street', 'billing_zipcode', 'billing_city', 'billing_country',
                'debtor_number', 'invoice_reference',
            ]);
        });

        Schema::table('people', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('relation_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('salutation')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('facebook_url')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('preferences'));
        Schema::table('people', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropConstrainedForeignId('relation_manager_id');
            $table->dropColumn(['salutation', 'twitter_url', 'facebook_url']);
        });
        Schema::dropIfExists('customer_groups');
    }
};
