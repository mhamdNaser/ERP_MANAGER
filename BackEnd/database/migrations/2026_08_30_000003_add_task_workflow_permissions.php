<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * صلاحيتان جديدتان لمسار المهام:
 *
 *  - tasks.review      تدقيق المهام القادمة من مرحلة التواصل واعتمادها نهائيًا
 *                      أو إعادتها إلى قيد التنفيذ.
 *  - tasks.statistics.view  لوحة إحصائيات المهام وسرعة إنجاز كل موظف.
 *
 * كلتاهما ممنوحة افتراضيًا لرئيس القسم ومدير الفرع (ومن فوقهما)، وتبقى قابلة
 * للمنح لاحقًا لموظف مشرف بعينه من صفحة الأدوار والصلاحيات.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['tasks.review', 'tasks.statistics.view'];

    private const ROLES = ['department_head', 'branch_manager', 'general_manager', 'database_manager'];

    public function up(): void
    {
        $permissions = collect(self::PERMISSIONS)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));

        Role::whereIn('name', self::ROLES)->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $permissions->each(fn (Permission $permission) => $role->givePermissionTo($permission)));

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get()
            ->each(function (Permission $permission): void {
                \Illuminate\Support\Facades\DB::table('model_has_permissions')->where('permission_id', $permission->id)->delete();
                \Illuminate\Support\Facades\DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();
                $permission->delete();
            });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
