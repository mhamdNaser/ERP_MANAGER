<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissions = [
        'formal_correspondences.view',
        'formal_correspondences.create',
        'formal_correspondences.route',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }

        $officeId = DB::table('offices')->where('code', 'REGISTRY')->value('id');

        if (! $officeId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $this->permissions)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('users')
            ->where('office_id', $officeId)
            ->pluck('id')
            ->each(function ($userId) use ($permissionIds) {
                $permissionIds->each(function ($permissionId) use ($userId) {
                    DB::table('model_has_permissions')->updateOrInsert([
                        'permission_id' => $permissionId,
                        'model_type' => 'App\\Models\\User',
                        'model_id' => $userId,
                    ]);
                });
            });
    }

    public function down(): void
    {
        $officeId = DB::table('offices')->where('code', 'REGISTRY')->value('id');

        if (! $officeId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $this->permissions)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('model_has_permissions')
            ->where('model_type', 'App\\Models\\User')
            ->whereIn('permission_id', $permissionIds)
            ->whereIn('model_id', DB::table('users')->where('office_id', $officeId)->pluck('id'))
            ->delete();
    }
};
