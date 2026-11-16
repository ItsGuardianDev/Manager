<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->json('permissions');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('user_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invitation_token')->nullable();
            $table->timestamp('invited_at')->nullable();
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->string('type', 20)->default('note');
        });
    }

    public function down(): void
    {
        Schema::table('notes', fn (Blueprint $table) => $table->dropColumn('type'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_group_id');
            $table->dropColumn(['invitation_token', 'invited_at']);
        });
        Schema::dropIfExists('user_groups');
    }
};
