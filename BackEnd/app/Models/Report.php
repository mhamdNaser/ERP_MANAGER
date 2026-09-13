<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    protected $fillable = [
        'employee_id', 'branch_id', 'department_id', 'type', 'period_start', 'period_end',
        'title', 'summary', 'achievements', 'challenges', 'next_steps', 'status',
        'current_reviewer_role', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'submitted_at' => 'datetime'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(User::class, 'employee_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function actions(): HasMany { return $this->hasMany(ReportAction::class)->latest(); }
}
