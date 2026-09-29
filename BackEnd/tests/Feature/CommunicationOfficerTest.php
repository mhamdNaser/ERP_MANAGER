<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/** قسم فيه رئيس ومكلَّف وعضوان، وكل ما يلزم لتحريك مهمة إلى التواصل. */
function officerFixture(string $suffix): array
{
    Permission::firstOrCreate(['name' => User::COMMUNICATION_PERMISSION, 'guard_name' => 'web']);

    $branch = Branch::create(['name' => 'Branch', 'code' => "CO-BR-{$suffix}"]);
    $department = Department::create(['name' => 'Department', 'code' => "CO-DP-{$suffix}", 'branch_id' => $branch->id]);

    $make = function (string $key, string $role) use ($branch, $department, $suffix) {
        $user = User::create([
            'name' => ucfirst($key), 'email' => "co-{$key}-{$suffix}@test.local",
            'password' => 'password', 'role' => $role,
            'branch_id' => $branch->id, 'department_id' => $department->id,
            'api_token' => hash('sha256', "co-{$key}-{$suffix}"),
        ]);
        $user->assignRole($role);

        return $user;
    };

    $head = $make('head', 'department_head');
    $assignee = $make('assignee', 'employee');
    $officer = $make('officer', 'employee');
    $plain = $make('plain', 'employee');

    $task = Task::create([
        'title' => 'Task', 'department_id' => $department->id,
        'assignee_id' => $assignee->id, 'creator_id' => $head->id,
        'status' => 'in_progress', 'position' => 0,
    ]);

    return compact('head', 'assignee', 'officer', 'plain', 'task', 'department');
}

it('lets any department member take the communication stage while no officer is assigned', function () {
    $this->seed(RolePermissionSeeder::class);
    ['plain' => $plain, 'task' => $task] = officerFixture('none');

    $this->withToken('co-assignee-none')->patchJson("/api/tasks/{$task->id}/move", [
        'status' => 'communication',
        'communication_user_id' => $plain->id,
    ])->assertOk()->assertJsonPath('status', 'communication');
});

it('restricts the communication stage to designated officers once one exists', function () {
    $this->seed(RolePermissionSeeder::class);
    ['officer' => $officer, 'plain' => $plain, 'task' => $task] = officerFixture('set');
    $officer->givePermissionTo(User::COMMUNICATION_PERMISSION);

    // غير المعيَّن يُرفض بعد أن صار في القسم موظف تواصل.
    $this->withToken('co-assignee-set')->patchJson("/api/tasks/{$task->id}/move", [
        'status' => 'communication',
        'communication_user_id' => $plain->id,
    ])->assertStatus(422);

    $this->withToken('co-assignee-set')->patchJson("/api/tasks/{$task->id}/move", [
        'status' => 'communication',
        'communication_user_id' => $officer->id,
    ])->assertOk()->assertJsonPath('communication_user.id', $officer->id);
});

it('grants and revokes the officer permission from the employee form', function () {
    $this->seed(RolePermissionSeeder::class);
    Permission::firstOrCreate(['name' => User::COMMUNICATION_PERMISSION, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => User::ASSIGN_COMMUNICATION_PERMISSION, 'guard_name' => 'web']);

    $manager = User::create([
        'name' => 'DB manager', 'email' => 'co-db@test.local', 'password' => 'password',
        'role' => 'database_manager', 'api_token' => hash('sha256', 'co-db'),
    ]);
    $manager->assignRole('database_manager');
    $manager->givePermissionTo(User::ASSIGN_COMMUNICATION_PERMISSION);

    $created = $this->withToken('co-db')->postJson('/api/employees', [
        'name' => 'Officer', 'email' => 'co-new@test.local', 'password' => 'password123',
        'role' => 'employee', 'is_communication_officer' => true,
    ])->assertCreated()->json();

    expect(User::find($created['id'])->isCommunicationOfficer())->toBeTrue();

    $this->withToken('co-db')->putJson("/api/employees/{$created['id']}", [
        'is_communication_officer' => false,
    ])->assertOk();

    expect(User::find($created['id'])->fresh()->isCommunicationOfficer())->toBeFalse();
});

it('ignores the officer flag from someone without the authority to assign it', function () {
    $this->seed(RolePermissionSeeder::class);
    Permission::firstOrCreate(['name' => User::COMMUNICATION_PERMISSION, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => User::ASSIGN_COMMUNICATION_PERMISSION, 'guard_name' => 'web']);

    // يملك إنشاء الموظفين لكنه لا يملك سلطة وصفه بموظف تواصل.
    $manager = User::create([
        'name' => 'Limited', 'email' => 'co-limited@test.local', 'password' => 'password',
        'role' => 'database_manager', 'api_token' => hash('sha256', 'co-limited'),
    ]);
    $manager->assignRole('database_manager');
    // السحب من الدور لا من الشخص: revokePermissionTo تمسّ المنح الفردي وحده،
    // والصلاحية هنا موروثة عن الدور.
    Role::findByName('database_manager')->revokePermissionTo(User::ASSIGN_COMMUNICATION_PERMISSION);
    $manager->forgetCachedPermissions();

    $created = $this->withToken('co-limited')->postJson('/api/employees', [
        'name' => 'Hopeful', 'email' => 'co-hopeful@test.local', 'password' => 'password123',
        'role' => 'employee', 'is_communication_officer' => true,
    ])->assertCreated()->json();

    expect(User::find($created['id'])->isCommunicationOfficer())->toBeFalse();
});
