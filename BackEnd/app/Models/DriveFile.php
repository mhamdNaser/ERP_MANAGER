<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DriveFile extends Model
{
    protected $fillable = [
        'uploader_id', 'department_id', 'task_id', 'folder_id', 'scope', 'name', 'path',
        'mime_type', 'size', 'description', 'public_token', 'public_path',
    ];

    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploader_id'); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function folder(): BelongsTo { return $this->belongsTo(DriveFolder::class); }
    public function sharedUsers(): BelongsToMany { return $this->belongsToMany(User::class, 'drive_file_shares')->withPivot('shared_by')->withTimestamps(); }
}
