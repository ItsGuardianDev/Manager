<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->string('category', 20)->nullable();
            $table->string('title');
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        foreach (DB::table('employee_documents')->get() as $row) {
            DB::table('documents')->insert([
                'documentable_type' => 'App\\Models\\Employee', 'documentable_id' => $row->employee_id, 'category' => $row->category,
                'title' => $row->title, 'path' => $row->path, 'original_name' => $row->original_name, 'size' => $row->size,
                'uploaded_by' => $row->uploaded_by, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
            ]);
        }

        Schema::dropIfExists('employee_documents');

        Schema::table('notes', function (Blueprint $table) {
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notes', fn (Blueprint $table) => $table->dropColumn(['attachment_path', 'attachment_name']));
        Schema::dropIfExists('documents');
    }
};
