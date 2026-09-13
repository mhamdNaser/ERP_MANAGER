<?php

namespace App\Modules\Forms\Resources;

use App\Modules\Employees\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomFormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'target_group' => $this->normalizeTargetGroup($this->target_group),
            'default_scope' => $this->default_scope,
            'default_duration_days' => $this->default_duration_days,
            'is_active' => $this->is_active,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'latest_publication' => new CustomFormPublicationResource($this->whenLoaded('latestPublication')),
            'fields' => CustomFormFieldResource::collection($this->whenLoaded('fields')),
            'submission_count' => $this->whenCounted('submissions'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function normalizeTargetGroup(string $group): string
    {
        return match ($group) {
            'employees' => 'branch_managers_heads_employees',
            'managers' => 'branch_managers_heads',
            default => $group,
        };
    }
}
