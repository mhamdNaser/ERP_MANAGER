<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FormalCorrespondenceStageResponse extends Model
{
    protected $fillable = [
        'formal_correspondence_event_id', 'actor_id', 'response_type',
        'body', 'recommendation',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size',
    ];

    protected $appends = ['attachment_url'];

    public function event(): BelongsTo { return $this->belongsTo(FormalCorrespondenceEvent::class, 'formal_correspondence_event_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    public function getAttachmentUrlAttribute(): ?string { return $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null; }
}
