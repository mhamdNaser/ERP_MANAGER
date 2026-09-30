<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ملف Excel رُفع إلى قسم الصيانة، محفوظاً كما هو مع خلاصة ما أُخذ منه. */
class MaintenanceImport extends Model
{
    protected $fillable = ['file_name', 'file_path', 'category_id', 'created_count', 'merged_count', 'skipped_count', 'actor_id'];

    public function category(): BelongsTo { return $this->belongsTo(MaintenanceCategory::class, 'category_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
