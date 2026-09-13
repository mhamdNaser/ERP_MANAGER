<?php

namespace App\Modules\Forms\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomFormFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field_key' => $this->field_key,
            'label' => $this->label,
            'input_type' => $this->input_type,
            'options' => $this->options,
            'placeholder' => $this->placeholder,
            'help_text' => $this->help_text,
            'is_required' => $this->is_required,
            'sort_order' => $this->sort_order,
        ];
    }
}
