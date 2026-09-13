<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomFormPublication extends Model
{
    protected $fillable = ['custom_form_id', 'issuer_id', 'scope', 'target_group', 'branch_id', 'department_id', 'visible_from', 'visible_until', 'status', 'message'];

    protected function casts(): array
    {
        return [
            'visible_from' => 'datetime',
            'visible_until' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CustomFormSubmission::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
