<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MessageReply extends Model
{
    protected $fillable = ['message_id', 'sender_id', 'content', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size'];
    protected $appends = ['attachment_url'];
    public function message(): BelongsTo { return $this->belongsTo(Message::class); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function getAttachmentUrlAttribute(): ?string { return $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null; }
}
