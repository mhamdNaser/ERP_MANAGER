<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFormField extends Model
{
    protected $fillable = ['custom_form_id', 'field_key', 'label', 'input_type', 'options', 'placeholder', 'help_text', 'is_required', 'sort_order'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }
}
