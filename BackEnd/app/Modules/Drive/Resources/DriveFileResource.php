<?php

namespace App\Modules\Drive\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DriveFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'description' => $this->description,
            'scope' => $this->scope,
            'folder_id' => $this->folder_id,
            'public_url' => $this->public_token ? url("/api/public-files/{$this->public_token}") : null,
            'direct_url' => $this->public_path ? Storage::disk('public')->url($this->public_path) : null,
            'download_url' => url("/api/drive-files/{$this->id}/download"),
            'preview_url' => url("/api/drive-files/{$this->id}/preview"),
            'uploader' => $this->whenLoaded('uploader', fn () => ['id' => $this->uploader->id, 'name' => $this->uploader->name, 'role' => $this->uploader->primaryRole()]),
            'department' => $this->whenLoaded('department', fn () => ['id' => $this->department->id, 'name' => $this->department->name]),
            'task' => $this->whenLoaded('task', fn () => ['id' => $this->task->id, 'title' => $this->task->title]),
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
