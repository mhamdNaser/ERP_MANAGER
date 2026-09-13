<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseMaintenanceLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'action', 'tables', 'source_file', 'created_at'];

    protected function casts(): array
    {
        return ['tables' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
