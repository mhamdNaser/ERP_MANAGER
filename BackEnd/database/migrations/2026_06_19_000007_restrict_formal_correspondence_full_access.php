<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'formal_correspondences.delete')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', ['branch_manager', 'database_manager'])
            ->pluck('id');

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $roleIds)
            ->delete();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'formal_correspondences.delete')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('roles')
            ->whereIn('name', ['branch_manager', 'database_manager'])
            ->pluck('id')
            ->each(function ($roleId) use ($permissionId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            });
    }
};
