<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drive_folders', function (Blueprint $table) {
            $table->string('path')->nullable()->after('name');
            $table->string('public_path')->nullable()->after('public_token');
        });

        Schema::table('drive_files', function (Blueprint $table) {
            $table->string('public_path')->nullable()->after('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('drive_files', fn (Blueprint $table) => $table->dropColumn('public_path'));
        Schema::table('drive_folders', fn (Blueprint $table) => $table->dropColumn(['path', 'public_path']));
    }
};
