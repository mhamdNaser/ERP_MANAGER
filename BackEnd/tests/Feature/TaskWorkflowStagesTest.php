<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * مسار المهمة الكامل: مؤرشفة ← مخطط لها ← قيد التنفيذ ⇄ التواصل ← التدقيق ←
 * الاعتماد، ومن يملك تحريك كل مرحلة.
 */
function workflowTeam(string $prefix): array
{
    test()->seed(RolePermissionSeeder::class);
    $branch = Branch::create(['name' => 'Branch', 'code' => "{$prefix}-BR"]);
    $department = Department::create(['name' => 'Department', 'code' => "{$prefix}-DP", 'branch_id' => $branch->id]);

    $make = function (string $name, string $role) use ($branch, $department, $prefix) {
        $user = User::create([
            'name' => $name,
            'email' => "{$prefix}-{$name}@test.local",
            'password' => 'password',
            'role' => $role,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'api_token' => hash('sha256', "{$prefix}-{$name}"),
        ]);
        $user->assignRole($role);

        return $user;
    };

    return [
        'department' => $department,
        'head' => $make('head', 'department_head'),
        'assignee' => $make('assignee', 'employee'),
        'officer' => $make('officer', 'employee'),
        'token' => fn (string $name) => "{$prefix}-{$name}",
    ];
}

it('starts a new task in the archived stage and lets only its assignee plan it', function () {
    $team = workflowTeam('WF1');

    $task = $this->withToken($team['token']('head'))->postJson('/api/tasks', [
        'department_id' => $team['department']->id,
        'assignee_id' => $team['assignee']->id,
        'title' => 'Stage walk',
    ])->assertCreated()->assertJsonPath('status', 'archived')->json();

    // رئيس القسم أنشأها لكن تحريكها من المراحل الأولى بيد المكلّف وحده.
    $this->withToken($team['token']('head'))->patchJson("/api/tasks/{$task['id']}/move", ['status' => 'planned'])
        ->assertForbidden();

    $this->withToken($team['token']('assignee'))->patchJson("/api/tasks/{$task['id']}/move", ['status' => 'planned'])
        ->assertOk()->assertJsonPath('status', 'planned');

    $this->assertDatabaseHas('task_stage_transitions', [
        'task_id' => $task['id'],
        'from_status' => 'archived',
        'to_status' => 'planned',
        'actor_id' => $team['assignee']->id,
    ]);
});

it('refuses a jump that skips the workflow', function () {
    $team = workflowTeam('WF2');

    $task = $this->withToken($team['token']('head'))->postJson('/api/tasks', [
        'department_id' => $team['department']->id,
        'assignee_id' => $team['assignee']->id,
        'title' => 'No shortcuts',
        'status' => 'planned',
    ])->assertCreated()->json();

    $this->withToken($team['token']('assignee'))->patchJson("/api/tasks/{$task['id']}/move", ['status' => 'review'])
        ->assertStatus(422);
});

it('routes a task through communication and back, then to review and approval', function () {
    $team = workflowTeam('WF3');

    $task = $this->withToken($team['token']('head'))->postJson('/api/tasks', [
        'department_id' => $team['department']->id,
        'assignee_id' => $team['assignee']->id,
        'title' => 'Full path',
        'status' => 'planned',
    ])->assertCreated()->json();
    $move = fn (string $token, array $payload) => $this->withToken($token)
        ->patchJson("/api/tasks/{$task['id']}/move", $payload);

    $move($team['token']('assignee'), ['status' => 'in_progress'])->assertOk();

    // التحويل إلى التواصل يشترط اختيار موظف التواصل.
    $move($team['token']('assignee'), ['status' => 'communication'])->assertStatus(422);
    $move($team['token']('assignee'), ['status' => 'communication', 'communication_user_id' => $team['officer']->id])
        ->assertOk()
        ->assertJsonPath('status', 'communication')
        ->assertJsonPath('communication_user.id', $team['officer']->id);

    // المكلّف لم يعد صاحب القرار في هذه المرحلة، وموظف التواصل لا يعيدها بلا ملاحظة.
    $move($team['token']('assignee'), ['status' => 'review'])->assertForbidden();
    $move($team['token']('officer'), ['status' => 'in_progress'])->assertStatus(422);
    $move($team['token']('officer'), ['status' => 'in_progress', 'note' => 'ينقص رقم العقد.'])
        ->assertOk()->assertJsonPath('status', 'in_progress');

    $this->assertDatabaseHas('task_stage_transitions', [
        'task_id' => $task['id'],
        'from_status' => 'communication',
        'to_status' => 'in_progress',
        'communication_user_id' => $team['officer']->id,
        'note' => 'ينقص رقم العقد.',
    ]);

    $move($team['token']('assignee'), ['status' => 'communication', 'communication_user_id' => $team['officer']->id])->assertOk();
    $move($team['token']('officer'), ['status' => 'review'])->assertOk()->assertJsonPath('status', 'review');

    // الاعتماد يتطلب صلاحية التدقيق، وهي ممنوحة لرئيس القسم لا للموظف.
    $move($team['token']('officer'), ['status' => 'completed'])->assertForbidden();
    $move($team['token']('head'), ['status' => 'completed'])->assertOk()->assertJsonPath('status', 'completed');

    $this->assertDatabaseMissing('tasks', ['id' => $task['id'], 'completed_at' => null]);
    expect(\App\Models\TaskStageTransition::where('task_id', $task['id'])->whereNotNull('from_status')->pluck('seconds_in_previous'))
        ->each->not->toBeNull();
});

it('exposes the task statistics board only to holders of tasks.statistics.view', function () {
    $team = workflowTeam('WF4');

    $this->withToken($team['token']('assignee'))->getJson('/api/task-statistics')->assertForbidden();

    $this->withToken($team['token']('head'))->getJson('/api/task-statistics')
        ->assertOk()
        ->assertJsonStructure(['summary' => ['total', 'approved', 'overdue', 'avg_cycle_hours', 'returns'], 'stages', 'employees', 'communicators', 'trend']);

    // الصلاحية قابلة للمنح لموظف مشرف بعينه دون تغيير دوره.
    $team['assignee']->givePermissionTo('tasks.statistics.view');
    $this->withToken($team['token']('assignee'))->getJson('/api/task-statistics')->assertOk();
});
