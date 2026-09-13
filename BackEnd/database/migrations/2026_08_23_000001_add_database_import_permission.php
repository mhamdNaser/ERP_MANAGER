<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * صلاحية منفصلة عن database.backups.manage لاستيراد بيانات جدول من ملف
 * إكسل — أخطر من التصدير/النسخ الاحتياطي لأنها تكتب بيانات فعلية.
 * تُمنح لمدير قواعد البيانات فقط، بنفس نمط database.maintenance.manage.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'database.import.manage', 'guard_name' => 'web']);
        Role::where('name', 'database_manager')->where('guard_name', 'web')->first()?->givePermissionTo($permission);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'database.import.manage')->where('guard_name', 'web')->first();
        if ($permission) {
            Role::where('name', 'database_manager')->where('guard_name', 'web')->first()?->revokePermissionTo($permission);
        }
    }
};
