<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * قسم الموارد البشرية: طلبات الإجازة والمغادرة والمهام والوثائق،
 * مع سجل قرارات ورصيد إجازات سنوي لكل موظف.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        // تُمنح لمنتسبي قسم الموارد البشرية مباشرةً، لا عبر الأدوار.
        'hr.view' => [],
        'hr.manage' => [],
        'hr.request' => ['database_manager', 'general_manager', 'branch_manager', 'department_head', 'office_manager', 'technician', 'employee'],
    ];

    public function up(): void
    {
        Schema::create('hr_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->index();                 // leave | departure | mission | document
            $table->string('subtype')->nullable();           // annual|sick|unpaid — employment|salary
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('days', 5, 1)->nullable();
            $table->decimal('hours', 4, 1)->nullable();
            $table->string('destination')->nullable();
            $table->text('reason');
            $table->string('status')->default('pending_head')->index();
            $table->string('stage')->default('head')->index(); // head | hr | gm | done
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hr_request_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage');
            $table->string('action');                        // approve | reject | submit | cancel
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('hr_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('annual_entitlement', 5, 1)->default(30);
            $table->decimal('used_days', 5, 1)->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'year']);
        });

        $this->grantPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys(self::PERMISSIONS))->delete();
        Schema::dropIfExists('hr_leave_balances');
        Schema::dropIfExists('hr_request_actions');
        Schema::dropIfExists('hr_requests');
    }

    private function grantPermissions(): void
    {
        foreach (self::PERMISSIONS as $name => $roles) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            foreach ($roles as $roleName) {
                Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
