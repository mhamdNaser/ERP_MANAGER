<?php

namespace App\Modules\Forms\Resources;

use App\Modules\Employees\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomFormPublicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->scope,
            'target_group' => $this->normalizeTargetGroup($this->target_group),
            'branch' => $this->whenLoaded('branch'),
            'department' => $this->whenLoaded('department'),
            'issuer' => new UserResource($this->whenLoaded('issuer')),
            'form' => new CustomFormResource($this->whenLoaded('form')),
            'visible_from' => $this->visible_from?->toIso8601String(),
            'visible_until' => $this->visible_until?->toIso8601String(),
            'status' => $this->status,
            'message' => $this->message,
            'published_at' => $this->created_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
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
