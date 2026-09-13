<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * تعديل بيانات المهمة (العنوان/الوصف/المسؤول/الأولوية...) كان متاحًا لأي موظف
 * يرى لوحة القسم دون أي صلاحية مخصصة. صار مضبوطًا بصلاحية tasks.update، ممنوحة
 * افتراضيًا لنفس الأدوار التي تملك tasks.create للحفاظ على السلوك الحالي،
 * وقابلة للتوسيع لاحقًا من صفحة الأدوار والصلاحيات أو لموظف محدد.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'tasks.update', 'guard_name' => 'web']);

        Role::whereIn('name', ['department_head', 'branch_manager', 'general_manager', 'database_manager'])
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'tasks.update')->where('guard_name', 'web')->first();
        if (! $permission) return;

        Role::whereIn('name', ['department_head', 'branch_manager', 'general_manager', 'database_manager'])
            ->where('guard_name', 'web')->get()->each(fn (Role $role) => $role->revokePermissionTo($permission));

        \Illuminate\Support\Facades\DB::table('model_has_permissions')->where('permission_id', $permission->id)->delete();
        $permission->delete();
    }
};
