<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * الموظفون لا ينشئون مهامًا ولا يحذفونها — فقط رئيس القسم/مدير الفرع/المدير
 * العام/مدير قواعد البيانات. المدير العام كان مفقودًا من صلاحية حذف المهام
 * أصلاً، فمُنحها هنا أيضًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        $create = Permission::firstOrCreate(['name' => 'tasks.create', 'guard_name' => 'web']);
        $delete = Permission::firstOrCreate(['name' => 'tasks.delete_with_activities', 'guard_name' => 'web']);

        Role::whereIn('name', ['department_head', 'branch_manager', 'general_manager', 'database_manager'])
            ->where('guard_name', 'web')
            ->get()
            ->each(function (Role $role) use ($create, $delete) {
                $role->givePermissionTo($create);
                $role->givePermissionTo($delete);
            });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $create = Permission::where('name', 'tasks.create')->where('guard_name', 'web')->first();
        if ($create) {
            Role::whereIn('name', ['department_head', 'branch_manager', 'general_manager', 'database_manager'])
                ->where('guard_name', 'web')->get()->each(fn (Role $role) => $role->revokePermissionTo($create));
        }

        $delete = Permission::where('name', 'tasks.delete_with_activities')->where('guard_name', 'web')->first();
        if ($delete) {
            Role::where('name', 'general_manager')->where('guard_name', 'web')->first()?->revokePermissionTo($delete);
        }
    }
};
