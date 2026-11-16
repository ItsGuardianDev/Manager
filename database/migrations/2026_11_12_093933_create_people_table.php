<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('initials')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name_prefix')->nullable();
            $table->string('last_name');
            $table->string('full_name_normalized')->index();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('street')->nullable();
            $table->string('zipcode')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('organization_person', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_person');
        Schema::dropIfExists('people');
    }
};
