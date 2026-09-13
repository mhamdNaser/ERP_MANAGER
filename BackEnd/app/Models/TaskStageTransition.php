<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskStageTransition extends Model
{
    protected $fillable = [
        'task_id', 'department_id', 'actor_id', 'assignee_id', 'communication_user_id',
        'from_status', 'to_status', 'seconds_in_previous', 'note',
    ];

    protected function casts(): array
    {
        return ['seconds_in_previous' => 'integer'];
    }

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); }
    public function communicationUser(): BelongsTo { return $this->belongsTo(User::class, 'communication_user_id'); }
}
