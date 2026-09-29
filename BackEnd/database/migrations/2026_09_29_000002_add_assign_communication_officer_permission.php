<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحية تعيين موظفي التواصل.
 *
 * منفصلة عن `tasks.communication` نفسها: تلك صفةٌ تُوصف بها الموظف، وهذه
 * سلطةُ وصفِه بها. تُمنح ابتداءً لمن يدير الموظفين على مستوى المؤسسة،
 * وتبقى قابلةً للمنح لرئيس قسم أو مدير فرع من صفحة الصلاحيات دون تعديل كود.
 *
 * من لا يملكها يرى فورم الموظف كاملاً إلا هذه الخانة، ولو أرسلها تجاهلها
 * الخادم — فلا يغيّر الصفة من لا سلطة له عليها.
 */
return new class extends Migration
{
    private const PERMISSION = 'tasks.communication.assign';

    private const ROLES = ['database_manager', 'general_manager'];

    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        foreach (self::ROLES as $role) {
            Role::where('name', $role)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
