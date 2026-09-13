<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * تبويب الشجرة التنظيمية (سحب وإفلات لنقل الأقسام بين الأفرع أو تحويلها
 * للإدارة مباشرة) مقيَّد بمدير قواعد البيانات وحده.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'organization.tree.manage', 'guard_name' => 'web']);
        Role::where('name', 'database_manager')->where('guard_name', 'web')->first()?->givePermissionTo($permission);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'organization.tree.manage')->where('guard_name', 'web')->first();
        if ($permission) {
            Role::where('name', 'database_manager')->where('guard_name', 'web')->first()?->revokePermissionTo($permission);
        }
    }
};
