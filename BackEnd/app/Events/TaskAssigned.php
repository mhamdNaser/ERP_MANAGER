<?php

namespace App\Events;

use App\Models\CndNotification;
use App\Models\Task;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskAssigned implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Task $task, public CndNotification $notification) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->notification->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'task.assigned';
    }

    public function broadcastWith(): array
    {
        $task = $this->task->loadMissing('department:id,name');

        return [
            'notification_id' => $this->notification->id,
            'title' => $this->notification->title,
            'message' => $this->notification->message,
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
                'department' => $task->department ? ['id' => $task->department->id, 'name' => $task->department->name] : null,
            ],
        ];
    }
}
