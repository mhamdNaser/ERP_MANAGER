<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'formal_correspondences.delete', 'guard_name' => 'web'],
            ['updated_at' => now(), 'created_at' => now()],
        );

        $permission = DB::table('permissions')
            ->where('name', 'formal_correspondences.delete')
            ->where('guard_name', 'web')
            ->first();

        if (! $permission) {
            return;
        }

        DB::table('roles')
            ->whereIn('name', ['branch_manager', 'general_manager', 'database_manager'])
            ->get()
            ->each(function ($role) use ($permission) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permission->id,
                    'role_id' => $role->id,
                ]);
            });
    }

    public function down(): void
    {
        $permission = DB::table('permissions')
            ->where('name', 'formal_correspondences.delete')
            ->where('guard_name', 'web')
            ->first();

        if (! $permission) {
            return;
        }

        DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();
        DB::table('permissions')->where('id', $permission->id)->delete();
    }
};
