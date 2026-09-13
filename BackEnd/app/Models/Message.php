<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    protected $fillable = ['sender_id', 'recipient_id', 'subject', 'content', 'purpose', 'allow_reply', 'read_at', 'last_reply_at', 'last_reply_sender_id', 'sender_read_at', 'recipient_read_at', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size'];
    protected function casts(): array { return ['allow_reply' => 'boolean', 'read_at' => 'datetime', 'last_reply_at' => 'datetime', 'sender_read_at' => 'datetime', 'recipient_read_at' => 'datetime']; }
    protected $appends = ['attachment_url'];
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_id'); }
    public function replies(): HasMany { return $this->hasMany(MessageReply::class); }
    public function getAttachmentUrlAttribute(): ?string { return $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null; }
}
