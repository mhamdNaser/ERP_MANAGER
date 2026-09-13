<?php
namespace App\Modules\Notifications\Resources;

use App\Modules\Communications\Resources\CircularResource;
use App\Modules\Forms\Resources\CustomFormPublicationResource;
use App\Modules\Forms\Resources\CustomFormResource;
use App\Modules\Reports\Resources\ReportResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sourceType = $this->report_id ? 'report' : ($this->circular_id ? 'circular' : ($this->custom_form_id ? 'form' : ($this->task_id ? 'task' : null)));
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'report_id' => $this->report_id,
            'circular_id' => $this->circular_id,
            'custom_form_id' => $this->custom_form_id,
            'custom_form_publication_id' => $this->custom_form_publication_id,
            'task_id' => $this->task_id,
            'source_type' => $sourceType,
            'report' => new ReportResource($this->whenLoaded('report')),
            'circular' => new CircularResource($this->whenLoaded('circular')),
            'custom_form' => new CustomFormResource($this->whenLoaded('customForm')),
            'custom_form_publication' => new CustomFormPublicationResource($this->whenLoaded('customFormPublication')),
            'task' => $this->whenLoaded('task', fn () => $this->task ? [
                'id' => $this->task->id,
                'title' => $this->task->title,
                'status' => $this->task->status,
                'priority' => $this->task->priority,
                'due_date' => $this->task->due_date?->toDateString(),
                'department' => $this->task->department ? ['id' => $this->task->department->id, 'name' => $this->task->department->name] : null,
            ] : null),
            'read_at' => $this->read_at?->toIso8601String(), 'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
