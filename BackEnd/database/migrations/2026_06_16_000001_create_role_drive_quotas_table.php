<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_drive_quotas', function (Blueprint $table) {
            $table->id();
            $table->string('role')->unique();
            $table->unsignedBigInteger('quota_bytes')->nullable();
            $table->timestamps();
        });

        $defaultQuota = 3 * 1024 * 1024 * 1024;
        $now = now();
        foreach (['employee', 'technician', 'department_head', 'branch_manager', 'general_manager'] as $role) {
            DB::table('role_drive_quotas')->insert([
                'role' => $role,
                'quota_bytes' => $defaultQuota,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('role_drive_quotas')->insert([
            'role' => 'database_manager',
            'quota_bytes' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_drive_quotas');
    }
};
