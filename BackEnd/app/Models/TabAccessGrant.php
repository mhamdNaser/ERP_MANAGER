<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** جمهور تبويب: الجميع، أو فرع، أو قسم، أو شخص — انظر config/tab_access.php. */
class TabAccessGrant extends Model
{
    public const LEVELS = ['view', 'manage'];

    protected $fillable = ['tab', 'everyone', 'branch_id', 'department_id', 'user_id', 'level', 'created_by_id'];

    protected function casts(): array
    {
        return ['everyone' => 'boolean'];
    }

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
