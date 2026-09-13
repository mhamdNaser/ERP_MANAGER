<?php
namespace App\Modules\Reports\Resources;

use App\Modules\Employees\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'type' => $this->type, 'title' => $this->title, 'summary' => $this->summary,
            'achievements' => $this->achievements, 'challenges' => $this->challenges, 'next_steps' => $this->next_steps,
            'status' => $this->status, 'period_start' => $this->period_start?->toDateString(), 'period_end' => $this->period_end?->toDateString(),
            'employee' => new UserResource($this->whenLoaded('employee')), 'branch' => $this->whenLoaded('branch'), 'department' => $this->whenLoaded('department'),
            'actions' => $this->whenLoaded('actions'), 'created_at' => $this->created_at?->toIso8601String(),
            'return_note' => $this->status === 'returned' ? Cache::get("reports:return-note:{$this->id}") : null,
        ];
    }
}
