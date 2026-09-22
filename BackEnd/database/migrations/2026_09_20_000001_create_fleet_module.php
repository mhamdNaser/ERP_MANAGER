<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * فرع الآليات: أول ما استلمه مهمة العمل المنقولة من الموارد البشرية،
 * بجداول وصلاحيات مستقلة تتسع لما يُضاف إلى الفرع لاحقاً.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        // تُمنح لمنتسبي فرع الآليات مباشرةً، لا عبر الأدوار — كما في الموارد البشرية.
        'fleet.view' => ['general_manager'],
        'fleet.manage' => [],
        'fleet.approve' => [],
        'fleet.request' => ['database_manager', 'general_manager', 'branch_manager', 'department_head', 'office_manager', 'technician', 'employee'],
    ];

    public function up(): void
    {
        Schema::create('fleet_missions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('mission')->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('days', 5, 1)->nullable();
            $table->string('destination')->nullable();
            $table->text('reason');
            $table->string('status')->default('pending_fleet')->index();
            $table->string('stage')->default('fleet')->index(); // fleet | gm | done
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('document_number')->nullable();
            $table->text('qr_payload')->nullable();
            $table->string('word_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::create('fleet_mission_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage');
            $table->string('action');                        // approve | reject | submit | cancel
            $table->text('note')->nullable();
            $table->timestamps();
        });

        $this->grantPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys(self::PERMISSIONS))->delete();
        Schema::dropIfExists('fleet_mission_actions');
        Schema::dropIfExists('fleet_missions');
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
