<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحية إدارة قوالب الوثائق. تُمنح لمدير قواعد البيانات في البيئات القائمة،
 * وهي صلاحية مستقلة لا دور، كي تُمنح لاحقاً لمن يُعهد إليه بالقوالب
 * (الموارد البشرية مثلاً) دون تعديل كود.
 */
return new class extends Migration
{
    private const PERMISSION = 'templates.manage';

    private const ROLES = ['database_manager'];

    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        foreach (self::ROLES as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
