<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'department_id', 'creator_id', 'assignee_id', 'communication_user_id', 'title', 'description',
        'status', 'priority', 'label', 'due_date', 'position', 'stage_entered_at', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'stage_entered_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'creator_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); }
    public function communicationUser(): BelongsTo { return $this->belongsTo(User::class, 'communication_user_id'); }
    public function files(): HasMany { return $this->hasMany(DriveFile::class); }
    public function activities(): HasMany { return $this->hasMany(TaskActivity::class); }
    public function transitions(): HasMany { return $this->hasMany(TaskStageTransition::class); }
}
