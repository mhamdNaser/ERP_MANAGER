<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class HrRequest extends Model
{
    public const TYPES = ['leave', 'departure', 'document'];

    /**
     * مسار الاعتماد: من الموظف إلى الموارد البشرية ثم المدير العام.
     * لا مرحلة لرئيس القسم؛ الطلبات موجّهة إلى الموارد البشرية مباشرة.
     */
    public const STAGES = ['hr', 'gm'];

    protected $fillable = [
        'reference_code', 'user_id', 'created_by_id', 'type', 'subtype',
        'start_date', 'end_date', 'start_time', 'end_time', 'days', 'hours',
        'destination', 'reason', 'status', 'stage',
        'attachment_path', 'attachment_name', 'decided_at',
        'document_number', 'qr_payload', 'word_path', 'pdf_path',
    ];

    protected $appends = ['attachment_url', 'word_url', 'pdf_url'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'decided_at' => 'datetime',
            'days' => 'float',
            'hours' => 'float',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_id'); }
    public function actions(): HasMany { return $this->hasMany(HrRequestAction::class)->orderBy('id'); }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null;
    }

    public function getWordUrlAttribute(): ?string { return $this->fileUrl($this->word_path); }

    public function getPdfUrlAttribute(): ?string { return $this->fileUrl($this->pdf_path); }

    private function fileUrl(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $url = Storage::disk('public')->url($path);

        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . ($this->updated_at?->timestamp ?: now()->timestamp);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['pending_head', 'pending_hr', 'pending_gm'], true);
    }
}
