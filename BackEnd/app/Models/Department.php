<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'branch_id', 'description', 'is_active'];
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
}
