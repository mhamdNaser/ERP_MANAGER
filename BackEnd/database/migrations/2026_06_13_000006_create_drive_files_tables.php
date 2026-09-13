<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploader_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->string('scope')->default('personal')->index();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->text('description')->nullable();
            $table->string('public_token', 80)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('drive_file_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drive_file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['drive_file_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_file_shares');
        Schema::dropIfExists('drive_files');
    }
};
