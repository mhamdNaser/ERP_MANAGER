<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Circular extends Model {
    protected $fillable=['issuer_id','title','content','audience','attachment_path','attachment_name','attachment_mime','attachment_size'];
    protected $appends=['attachment_url'];
    public function issuer(): BelongsTo { return $this->belongsTo(User::class,'issuer_id'); }
    public function recipients(): BelongsToMany { return $this->belongsToMany(User::class,'circular_recipients')->withPivot('read_at'); }
    public function getAttachmentUrlAttribute(): ?string { return $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null; }
}
