<?php

namespace App\Modules\Drive\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DriveFolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'description' => $this->description,
            'scope' => $this->scope,
            'public_url' => $this->public_token ? url("/api/public-folders/{$this->public_token}") : null,
            'direct_url' => $this->public_path ? Storage::disk('public')->url($this->public_path) : null,
            'owner' => $this->whenLoaded('owner', fn () => ['id' => $this->owner->id, 'name' => $this->owner->name, 'role' => $this->owner->primaryRole()]),
            'department' => $this->whenLoaded('department', fn () => ['id' => $this->department->id, 'name' => $this->department->name]),
            'files_count' => $this->whenCounted('files'),
            'children_count' => $this->whenCounted('children'),
            'shared_users_count' => $this->whenCounted('sharedUsers'),
            'shared_users' => $this->whenLoaded('sharedUsers', fn () => $this->sharedUsers->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'job_title' => $user->job_title,
                'branch' => $user->branch ? ['id' => $user->branch->id, 'name' => $user->branch->name] : null,
                'department' => $user->department ? ['id' => $user->department->id, 'name' => $user->department->name] : null,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
