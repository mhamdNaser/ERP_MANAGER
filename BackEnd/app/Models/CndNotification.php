<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Circular;

class CndNotification extends Model
{
    protected $table = 'cnd_notifications';

    protected $fillable = ['user_id', 'title', 'message', 'report_id', 'circular_id', 'custom_form_id', 'custom_form_publication_id', 'task_id', 'read_at'];
    protected function casts(): array { return ['read_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function report(): BelongsTo { return $this->belongsTo(Report::class); }
    public function circular(): BelongsTo { return $this->belongsTo(Circular::class); }
    public function customForm(): BelongsTo { return $this->belongsTo(CustomForm::class, 'custom_form_id'); }
    public function customFormPublication(): BelongsTo { return $this->belongsTo(CustomFormPublication::class, 'custom_form_publication_id'); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
}
