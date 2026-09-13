<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class FormalCorrespondence extends Model
{
    protected $fillable = [
        'reference_code', 'parent_id', 'direction', 'status', 'subject', 'summary', 'creator_id',
        'source_type', 'source_id', 'source_label',
        'target_type', 'target_id', 'target_label',
        'sender_external_entity_id', 'recipient_external_entity_id',
        'recipient_branch_id', 'recipient_department_id', 'first_place', 'body',
        'attachment_path', 'attachment_name', 'word_path', 'pdf_path',
        'qr_payload', 'issued_at',
    ];

    protected function casts(): array { return ['issued_at' => 'datetime']; }
    protected $appends = ['attachment_url'];
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'creator_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function senderExternalEntity(): BelongsTo { return $this->belongsTo(ExternalEntity::class, 'sender_external_entity_id'); }
    public function recipientExternalEntity(): BelongsTo { return $this->belongsTo(ExternalEntity::class, 'recipient_external_entity_id'); }
    public function recipientBranch(): BelongsTo { return $this->belongsTo(Branch::class, 'recipient_branch_id'); }
    public function recipientDepartment(): BelongsTo { return $this->belongsTo(Department::class, 'recipient_department_id'); }
    public function events(): HasMany { return $this->hasMany(FormalCorrespondenceEvent::class); }
    public function documents(): HasMany { return $this->hasMany(FormalCorrespondenceDocument::class); }
    public function getAttachmentUrlAttribute(): ?string { return $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null; }
}
