<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = ['name', 'code', 'description', 'is_active'];
    public function departments(): HasMany { return $this->hasMany(Department::class); }
}
