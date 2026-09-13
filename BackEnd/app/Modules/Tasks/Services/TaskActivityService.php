<?php

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Modules\Tasks\Repositories\Interfaces\TaskRepositoryInterface;

/** يصوغ سطر سجل الحركة؛ الكتابة نفسها في المستودع. */
class TaskActivityService
{
    public function __construct(private TaskRepositoryInterface $tasks) {}

    public function record(Task $task, User $actor, string $action, string $summary, array $details = []): TaskActivity
    {
        return $this->tasks->recordActivity([
            'task_id' => $task->id,
            'department_id' => $task->department_id,
            'actor_id' => $actor->id,
            'action' => $action,
            'task_title' => $task->title,
            'summary' => $summary,
            'details' => $details ?: null,
        ]);
    }
}
