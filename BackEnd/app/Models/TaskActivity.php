<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskActivity extends Model
{
    protected $fillable = [
        'task_id', 'department_id', 'actor_id', 'action', 'task_title', 'summary', 'details',
    ];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
