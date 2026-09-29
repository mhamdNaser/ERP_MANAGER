<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * صفة «موظف تواصل».
 *
 * صلاحية لا دور: عمود الدور مفرد ويحمل موقع الموظف في الهيكل (رئيس قسم،
 * مدير فرع…)، والموظف يكون رئيس قسم وموظف تواصل معاً — فلا تتسع له خانة
 * واحدة. ولا عمود منطقي جديد: المنح الفردي موجود أصلاً في
 * model_has_permissions وله واجهته، فتُمنح هذه كما تُمنح أخواتها.
 *
 * تبقى غير مرتبطة بأي دور عمداً: تُمنح لأشخاص بأعيانهم من فورم الموظف.
 */
return new class extends Migration
{
    private const PERMISSION = 'tasks.communication';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
