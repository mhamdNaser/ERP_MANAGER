<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * المدير العام يرى تبويب الموارد البشرية ليبتّ فيما وصل إليه،
 * دون صلاحية إدارة القسم (hr.manage) التي تبقى لمنتسبيه.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'hr.view', 'guard_name' => 'web']);
        Role::where('name', 'general_manager')->where('guard_name', 'web')->first()?->givePermissionTo($permission);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'hr.view')->where('guard_name', 'web')->first();
        if ($permission) {
            Role::where('name', 'general_manager')->where('guard_name', 'web')->first()?->revokePermissionTo($permission);
        }
    }
};
