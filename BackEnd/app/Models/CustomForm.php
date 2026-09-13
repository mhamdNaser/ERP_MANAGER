<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomForm extends Model
{
    protected $fillable = ['creator_id', 'title', 'description', 'target_group', 'default_scope', 'default_duration_days', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CustomFormField::class)->orderBy('sort_order');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(CustomFormPublication::class);
    }

    public function latestPublication(): HasOne
    {
        return $this->hasOne(CustomFormPublication::class)->latestOfMany();
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CustomFormSubmission::class);
    }
}
