<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('drive_folders')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope')->default('personal')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('public_token', 80)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('drive_folder_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drive_folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['drive_folder_id', 'user_id']);
        });

        Schema::table('drive_files', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('task_id')->constrained('drive_folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('drive_files', fn (Blueprint $table) => $table->dropConstrainedForeignId('folder_id'));
        Schema::dropIfExists('drive_folder_shares');
        Schema::dropIfExists('drive_folders');
    }
};
