<?php

use App\Events\TaskAssigned;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function assignmentFixtures(): array
{
    $branch = Branch::create(['name' => 'Branch', 'code' => 'ASSIGN-BR']);
    $department = Department::create(['name' => 'Department', 'code' => 'ASSIGN-DP', 'branch_id' => $branch->id]);
    $head = User::create(['name' => 'Head', 'email' => 'assign-head@test.local', 'password' => 'password', 'role' => 'department_head', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'assign-head')]);
    $head->assignRole('department_head');
    $employee = User::create(['name' => 'Employee', 'email' => 'assign-employee@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $department->id]);
    $otherEmployee = User::create(['name' => 'Other', 'email' => 'assign-other@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $department->id]);

    return compact('department', 'head', 'employee', 'otherEmployee');
}

it('notifies and broadcasts when a task is created with an assignee', function () {
    Event::fake([TaskAssigned::class]);
    $this->seed(RolePermissionSeeder::class);
    ['department' => $department, 'head' => $head, 'employee' => $employee] = assignmentFixtures();

    $task = $this->withToken('assign-head')->postJson('/api/tasks', [
        'department_id' => $department->id,
        'assignee_id' => $employee->id,
        'title' => 'Prepare the report',
    ])->assertCreated()->json();

    $this->assertDatabaseHas('cnd_notifications', [
        'user_id' => $employee->id,
        'task_id' => $task['id'],
    ]);
    Event::assertDispatched(TaskAssigned::class, fn (TaskAssigned $event) => $event->task->id === $task['id'] && $event->notification->user_id === $employee->id);
});

it('notifies again when a task is reassigned to a different employee', function () {
    Event::fake([TaskAssigned::class]);
    $this->seed(RolePermissionSeeder::class);
    ['department' => $department, 'head' => $head, 'employee' => $employee, 'otherEmployee' => $other] = assignmentFixtures();

    $task = $this->withToken('assign-head')->postJson('/api/tasks', [
        'department_id' => $department->id,
        'assignee_id' => $employee->id,
        'title' => 'Prepare the report',
    ])->assertCreated()->json();

    $this->withToken('assign-head')->putJson("/api/tasks/{$task['id']}", ['assignee_id' => $other->id])->assertOk();

    $this->assertDatabaseHas('cnd_notifications', ['user_id' => $other->id, 'task_id' => $task['id']]);
    Event::assertDispatchedTimes(TaskAssigned::class, 2);
});

it('does not notify when a task is created without an assignee', function () {
    Event::fake([TaskAssigned::class]);
    $this->seed(RolePermissionSeeder::class);
    ['department' => $department] = assignmentFixtures();

    $this->withToken('assign-head')->postJson('/api/tasks', [
        'department_id' => $department->id,
        'title' => 'Unassigned task',
    ])->assertCreated();

    Event::assertNotDispatched(TaskAssigned::class);
});
