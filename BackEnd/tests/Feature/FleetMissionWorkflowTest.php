<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** فرع الآليات وموظفوه ومقدّم المهمة — الحد الأدنى لتشغيل المسار. */
function fleetFixture(string $suffix): array
{
    $branch = Branch::create(['name' => 'Branch', 'code' => "FL-BR-{$suffix}"]);
    $fleetDepartment = Department::create(['name' => 'فرع الآليات', 'code' => "FLEET-{$suffix}", 'branch_id' => $branch->id]);
    $staffDepartment = Department::create(['name' => 'Department', 'code' => "FL-DP-{$suffix}", 'branch_id' => $branch->id]);

    $employee = User::create(['name' => 'Employee', 'email' => "fl-employee-{$suffix}@test.local", 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $staffDepartment->id, 'api_token' => hash('sha256', "fl-employee-{$suffix}")]);
    $employee->assignRole('employee');

    $fleetOfficer = User::create(['name' => 'Fleet officer', 'email' => "fl-officer-{$suffix}@test.local", 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $fleetDepartment->id, 'api_token' => hash('sha256', "fl-officer-{$suffix}")]);
    $fleetOfficer->assignRole('employee');
    $fleetOfficer->givePermissionTo(['fleet.view', 'fleet.manage', 'fleet.request', 'fleet.approve']);

    $generalManager = User::create(['name' => 'General manager', 'email' => "fl-gm-{$suffix}@test.local", 'password' => 'password', 'role' => 'general_manager', 'branch_id' => $branch->id, 'api_token' => hash('sha256', "fl-gm-{$suffix}")]);
    $generalManager->assignRole('general_manager');

    return compact('employee', 'fleetOfficer', 'generalManager');
}

it('walks a work mission from the employee through the fleet branch to the general manager', function () {
    $this->seed(RolePermissionSeeder::class);
    fleetFixture('walk');

    $mission = $this->withToken('fl-employee-walk')->postJson('/api/fleet/missions', [
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-03',
        'destination' => 'فرع حلب',
        'reason' => 'مهمة فنية لمتابعة تركيب المعدات.',
    ])->assertCreated()
        ->assertJsonPath('stage', 'fleet')
        ->assertJsonPath('status', 'pending_fleet')
        ->assertJsonPath('days', 3)
        ->json();

    $this->withToken('fl-officer-walk')->postJson("/api/fleet/missions/{$mission['id']}/decision", ['action' => 'approve'])
        ->assertOk()
        ->assertJsonPath('stage', 'gm')
        ->assertJsonPath('status', 'pending_gm');

    $this->withToken('fl-gm-walk')->postJson("/api/fleet/missions/{$mission['id']}/decision", ['action' => 'approve'])
        ->assertOk()
        ->assertJsonPath('stage', 'done')
        ->assertJsonPath('status', 'approved');
});

it('keeps missions out of sight of employees other than their owner', function () {
    $this->seed(RolePermissionSeeder::class);
    fleetFixture('scope');

    $mission = $this->withToken('fl-employee-scope')->postJson('/api/fleet/missions', [
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-01',
        'destination' => 'فرع حمص',
        'reason' => 'نقل تجهيزات.',
    ])->assertCreated()->json();

    // الموظف لا يملك fleet.view فلا يفتح لوحة الفرع، ولا يبتّ في مهمته.
    $this->withToken('fl-employee-scope')->getJson('/api/fleet/missions')->assertForbidden();
    $this->withToken('fl-employee-scope')->postJson("/api/fleet/missions/{$mission['id']}/decision", ['action' => 'approve'])->assertForbidden();

    $this->withToken('fl-employee-scope')->getJson('/api/fleet/my-missions')
        ->assertOk()
        ->assertJsonCount(1, 'missions');

    $this->withToken('fl-officer-scope')->getJson('/api/fleet/missions')
        ->assertOk()
        ->assertJsonPath('is_fleet_staff', true)
        ->assertJsonPath('summary.awaiting_fleet', 1);
});

it('no longer accepts a work mission as a human-resources request', function () {
    $this->seed(RolePermissionSeeder::class);
    fleetFixture('hr');

    $this->withToken('fl-employee-hr')->postJson('/api/hr/requests', [
        'type' => 'mission',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-02',
        'destination' => 'فرع حلب',
        'reason' => 'مهمة فنية.',
    ])->assertStatus(422)->assertJsonValidationErrors('type');
});
