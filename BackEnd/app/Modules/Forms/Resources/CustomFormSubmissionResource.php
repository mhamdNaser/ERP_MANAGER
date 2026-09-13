<?php

namespace App\Modules\Forms\Resources;

use App\Modules\Employees\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomFormSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'payload' => $this->payload,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'form' => new CustomFormResource($this->whenLoaded('form')),
            'publication' => new CustomFormPublicationResource($this->whenLoaded('publication')),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
