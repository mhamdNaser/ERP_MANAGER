<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('grants a database manager direct permissions to an employee beyond their role', function () {
    $this->seed(RolePermissionSeeder::class);
    $branch = Branch::create(['name' => 'Branch', 'code' => 'PERM-BR']);
    $department = Department::create(['name' => 'Department', 'code' => 'PERM-DP', 'branch_id' => $branch->id]);
    $manager = User::create(['name' => 'DB Manager', 'email' => 'perm-manager@test.local', 'password' => 'password', 'role' => 'database_manager', 'api_token' => hash('sha256', 'perm-manager-token')]);
    $manager->assignRole('database_manager');
    $employee = User::create(['name' => 'Employee', 'email' => 'perm-employee@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $department->id]);

    $response = $this->withToken('perm-manager-token')->putJson("/api/employees/{$employee->id}/permissions", [
        'permissions' => ['circulars.create'],
    ])->assertOk();

    expect($response->json('direct_permissions'))->toContain('circulars.create');
    expect($response->json('permissions'))->toContain('circulars.create');
    $this->assertDatabaseHas('model_has_permissions', ['model_id' => $employee->id]);
});

it('forbids employees without roles.permissions.manage from setting direct permissions', function () {
    $this->seed(RolePermissionSeeder::class);
    $branch = Branch::create(['name' => 'Branch', 'code' => 'PERM-BR2']);
    $department = Department::create(['name' => 'Department', 'code' => 'PERM-DP2', 'branch_id' => $branch->id]);
    $head = User::create(['name' => 'Head', 'email' => 'perm-head@test.local', 'password' => 'password', 'role' => 'department_head', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'perm-head-token')]);
    $head->assignRole('department_head');
    $employee = User::create(['name' => 'Employee', 'email' => 'perm-employee-2@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $department->id]);

    $this->withToken('perm-head-token')->putJson("/api/employees/{$employee->id}/permissions", [
        'permissions' => ['circulars.create'],
    ])->assertForbidden();
});
