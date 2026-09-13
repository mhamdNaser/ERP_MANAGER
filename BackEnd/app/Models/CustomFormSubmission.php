<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFormSubmission extends Model
{
    protected $fillable = ['custom_form_id', 'custom_form_publication_id', 'user_id', 'version', 'payload', 'submitted_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(CustomFormPublication::class, 'custom_form_publication_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
