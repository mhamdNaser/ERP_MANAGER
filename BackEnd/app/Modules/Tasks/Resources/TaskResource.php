<?php

namespace App\Modules\Tasks\Resources;

use App\Modules\Drive\Resources\DriveFileResource;
use App\Modules\Tasks\Services\TaskWorkflow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $allowedTransitions = app(TaskWorkflow::class)->allowedTargets($this->resource, $request->user());

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'label' => $this->label,
            'due_date' => $this->due_date?->toDateString(),
            'position' => $this->position,
            'stage_entered_at' => $this->stage_entered_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'department' => $this->whenLoaded('department', fn () => ['id' => $this->department->id, 'name' => $this->department->name]),
            'creator' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'assignee' => $this->whenLoaded('assignee', fn () => ['id' => $this->assignee->id, 'name' => $this->assignee->name, 'job_title' => $this->assignee->job_title]),
            'communication_user' => $this->whenLoaded('communicationUser', fn () => $this->communicationUser ? [
                'id' => $this->communicationUser->id,
                'name' => $this->communicationUser->name,
                'job_title' => $this->communicationUser->job_title,
            ] : null),
            'files' => DriveFileResource::collection($this->whenLoaded('files')),
            'activities' => $this->whenLoaded('activities', fn () => $this->activities->map(fn ($activity) => [
                'id' => $activity->id,
                'action' => $activity->action,
                'summary' => $activity->summary,
                'task_id' => $activity->task_id,
                'task_title' => $activity->task_title,
                'details' => $activity->details,
                'actor' => $activity->actor ? [
                    'id' => $activity->actor->id,
                    'name' => $activity->actor->name,
                    'job_title' => $activity->actor->job_title,
                ] : null,
                'department' => $this->relationLoaded('department') && $this->department ? [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                ] : null,
                'created_at' => $activity->created_at?->toISOString(),
            ])),
            // مسار المهمة عبر المراحل: كل انتقال بوقته ومدة المرحلة التي سبقته.
            'transitions' => $this->whenLoaded('transitions', fn () => $this->transitions->map(fn ($transition) => [
                'id' => $transition->id,
                'from_status' => $transition->from_status,
                'to_status' => $transition->to_status,
                'seconds_in_previous' => $transition->seconds_in_previous,
                'note' => $transition->note,
                'actor' => $transition->actor ? [
                    'id' => $transition->actor->id,
                    'name' => $transition->actor->name,
                    'job_title' => $transition->actor->job_title,
                ] : null,
                'created_at' => $transition->created_at?->toISOString(),
            ])),
            'can_delete' => $this->canManage($request->user()),
            // الواجهة لا تحسب مسار العمل بنفسها: تعرض فقط ما يسمح به الخادم
            // لهذا المستخدم من هذه المرحلة.
            'allowed_transitions' => $allowedTransitions,
            'can_move' => $allowedTransitions !== [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function canManage($user): bool
    {
        return $user?->can('tasks.delete_with_activities') ?? false;
    }
}
