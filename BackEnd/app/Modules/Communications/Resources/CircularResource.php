<?php
namespace App\Modules\Communications\Resources;

use App\Modules\Employees\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CircularResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'audience' => $this->audience,
            'attachment_name' => $this->attachment_name,
            'attachment_url' => $this->attachment_url,
            'attachment_mime' => $this->attachment_mime,
            'attachment_size' => $this->attachment_size,
            'issuer' => new UserResource($this->whenLoaded('issuer')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
