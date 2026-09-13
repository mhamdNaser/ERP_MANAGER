<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class FormalCorrespondenceEvent extends Model
{
    protected $fillable = [
        'formal_correspondence_id', 'actor_id',
        'assigned_user_id', 'assigned_by_id', 'assigned_at', 'task_id',
        'source_type', 'source_id', 'source_label',
        'target_type', 'target_id', 'target_label',
        'action_required', 'decision_type', 'decision_status', 'responded_at',
        'registry_number', 'letter_title', 'letter_body', 'book_number', 'qr_payload',
        'word_path', 'pdf_path',
        'event', 'from_status', 'to_status', 'note', 'meta',
    ];

    protected $appends = ['word_url', 'pdf_url'];
    protected function casts(): array { return ['meta' => 'array', 'responded_at' => 'datetime', 'assigned_at' => 'datetime']; }
    public function formalCorrespondence(): BelongsTo { return $this->belongsTo(FormalCorrespondence::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function assignedBy(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by_id'); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class, 'task_id'); }

    public function getWordUrlAttribute(): ?string { return $this->fileUrl($this->word_path); }

    public function getPdfUrlAttribute(): ?string { return $this->fileUrl($this->pdf_path); }

    /** رابط موسوم بوقت التعديل كي لا يخدم المتصفح نسخة قديمة بعد إعادة التوليد. */
    private function fileUrl(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $url = Storage::disk('public')->url($path);

        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . ($this->updated_at?->timestamp ?: now()->timestamp);
    }
    public function documents(): HasMany { return $this->hasMany(FormalCorrespondenceDocument::class); }
    public function responses(): HasMany { return $this->hasMany(FormalCorrespondenceStageResponse::class); }
}
