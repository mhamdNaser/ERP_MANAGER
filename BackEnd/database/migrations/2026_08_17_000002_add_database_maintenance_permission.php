<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * صلاحية منفصلة وأخطر من database.backups.manage لإفراغ الجداول واستعادتها.
 * تُمنح لمدير قواعد البيانات فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'database.maintenance.manage', 'guard_name' => 'web']);
        Role::where('name', 'database_manager')->where('guard_name', 'web')->first()?->givePermissionTo($permission);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'database.maintenance.manage')->where('guard_name', 'web')->first();
        if ($permission) {
            Role::where('name', 'database_manager')->where('guard_name', 'web')->first()?->revokePermissionTo($permission);
        }
    }
};
