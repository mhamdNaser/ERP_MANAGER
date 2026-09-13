<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FormalCorrespondenceDocument extends Model
{
    protected $fillable = [
        'formal_correspondence_id', 'formal_correspondence_event_id',
        'document_type', 'title', 'source_label', 'target_label', 'body', 'registry_number',
        'book_number', 'qr_payload',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size',
    ];

    protected $appends = ['attachment_url', 'pdf_url'];

    public function formalCorrespondence(): BelongsTo { return $this->belongsTo(FormalCorrespondence::class); }
    public function event(): BelongsTo { return $this->belongsTo(FormalCorrespondenceEvent::class, 'formal_correspondence_event_id'); }
    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return $this->urlWithVersion($this->attachment_path);
    }

    public function getPdfUrlAttribute(): ?string
    {
        if (! $this->attachment_path || ! str_ends_with(strtolower($this->attachment_path), '.docx')) {
            return null;
        }

        $pdfPath = preg_replace('/\.docx$/i', '.pdf', $this->attachment_path);

        return $pdfPath && Storage::disk('public')->exists($pdfPath)
            ? $this->urlWithVersion($pdfPath)
            : null;
    }

    private function urlWithVersion(string $path): string
    {
        $url = Storage::disk('public')->url($path);
        $version = $this->updated_at?->timestamp ?: now()->timestamp;

        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $version;
    }
}
