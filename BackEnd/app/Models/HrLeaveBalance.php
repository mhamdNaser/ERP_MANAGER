<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeaveBalance extends Model
{
    protected $fillable = ['user_id', 'year', 'annual_entitlement', 'used_days'];

    protected $appends = ['remaining_days'];

    protected function casts(): array
    {
        return ['annual_entitlement' => 'float', 'used_days' => 'float', 'year' => 'integer'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function getRemainingDaysAttribute(): float
    {
        return round($this->annual_entitlement - $this->used_days, 1);
    }
}
