<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriveFolder extends Model
{
    protected $fillable = ['owner_id', 'parent_id', 'department_id', 'scope', 'name', 'path', 'description', 'public_token', 'public_path'];

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function files(): HasMany { return $this->hasMany(DriveFile::class, 'folder_id'); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function sharedUsers(): BelongsToMany { return $this->belongsToMany(User::class, 'drive_folder_shares')->withPivot('shared_by')->withTimestamps(); }
}
