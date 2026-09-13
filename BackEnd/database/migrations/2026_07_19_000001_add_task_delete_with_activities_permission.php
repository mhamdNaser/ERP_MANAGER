<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $permission = 'tasks.delete_with_activities';

    private array $roles = ['department_head', 'branch_manager', 'database_manager'];

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => $this->permission, 'guard_name' => 'web'],
            ['updated_at' => now(), 'created_at' => now()],
        );

        $permissionId = DB::table('permissions')
            ->where('name', $this->permission)
            ->where('guard_name', 'web')
            ->value('id');

        DB::table('roles')->whereIn('name', $this->roles)->where('guard_name', 'web')
            ->pluck('id')
            ->each(fn ($roleId) => DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]));
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', $this->permission)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
