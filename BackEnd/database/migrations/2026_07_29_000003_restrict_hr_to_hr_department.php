<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * صلاحيات الموارد البشرية تُمنح لمنتسبي القسم مباشرةً لا عبر الأدوار،
 * فلا يطّلع مدير قواعد البيانات ولا رؤساء الأقسام على الطلبات.
 */
return new class extends Migration
{
    private const REVOKE = ['hr.view', 'hr.manage', 'hr.approve'];

    public function up(): void
    {
        foreach (self::REVOKE as $name) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if (! $permission) continue;

            foreach (Role::where('guard_name', 'web')->get() as $role) {
                if ($role->hasPermissionTo($permission)) {
                    $role->revokePermissionTo($permission);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (['hr.view', 'hr.manage'] as $name) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();
            Role::where('name', 'database_manager')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }
    }
};
