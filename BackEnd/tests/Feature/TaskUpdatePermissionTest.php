<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets a department head edit a task by default via the granted role permission', function () {
    $this->seed(RolePermissionSeeder::class);
    $branch = Branch::create(['name' => 'Branch', 'code' => 'UPD-BR']);
    $department = Department::create(['name' => 'Department', 'code' => 'UPD-DP', 'branch_id' => $branch->id]);
    $head = User::create(['name' => 'Head', 'email' => 'upd-head@test.local', 'password' => 'password', 'role' => 'department_head', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'upd-head')]);
    $head->assignRole('department_head');

    $task = $this->withToken('upd-head')->postJson('/api/tasks', ['department_id' => $department->id, 'title' => 'Original title'])->assertCreated()->json();

    $this->withToken('upd-head')->putJson("/api/tasks/{$task['id']}", ['title' => 'Updated title'])
        ->assertOk()->assertJsonPath('title', 'Updated title');
});

it('blocks a plain employee from editing a task without the tasks.update permission', function () {
    $this->seed(RolePermissionSeeder::class);
    $branch = Branch::create(['name' => 'Branch', 'code' => 'UPD-BR2']);
    $department = Department::create(['name' => 'Department', 'code' => 'UPD-DP2', 'branch_id' => $branch->id]);
    $head = User::create(['name' => 'Head', 'email' => 'upd-head2@test.local', 'password' => 'password', 'role' => 'department_head', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'upd-head2')]);
    $head->assignRole('department_head');
    $employee = User::create(['name' => 'Employee', 'email' => 'upd-employee@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'upd-employee')]);
    $employee->assignRole('employee');

    $task = $this->withToken('upd-head2')->postJson('/api/tasks', ['department_id' => $department->id, 'title' => 'Original title'])->assertCreated()->json();

    $this->withToken('upd-employee')->putJson("/api/tasks/{$task['id']}", ['title' => 'Hacked title'])->assertForbidden();
});

it('lets a plain employee edit a task once granted tasks.update directly', function () {
    $this->seed(RolePermissionSeeder::class);
    $branch = Branch::create(['name' => 'Branch', 'code' => 'UPD-BR3']);
    $department = Department::create(['name' => 'Department', 'code' => 'UPD-DP3', 'branch_id' => $branch->id]);
    $head = User::create(['name' => 'Head', 'email' => 'upd-head3@test.local', 'password' => 'password', 'role' => 'department_head', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'upd-head3')]);
    $head->assignRole('department_head');
    $employee = User::create(['name' => 'Employee', 'email' => 'upd-employee2@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'upd-employee2')]);
    $employee->assignRole('employee');
    $employee->givePermissionTo('tasks.update');

    $task = $this->withToken('upd-head3')->postJson('/api/tasks', ['department_id' => $department->id, 'title' => 'Original title'])->assertCreated()->json();

    $this->withToken('upd-employee2')->putJson("/api/tasks/{$task['id']}", ['title' => 'Updated by grantee'])
        ->assertOk()->assertJsonPath('title', 'Updated by grantee');
});
