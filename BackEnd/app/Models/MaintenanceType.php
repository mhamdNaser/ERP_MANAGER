<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** نوع القطعة داخل فئتها: IC وترانزستور وشاشة تحت «قطع إلكترونية» مثلاً. */
class MaintenanceType extends Model
{
    protected $fillable = ['category_id', 'name'];

    public function category(): BelongsTo { return $this->belongsTo(MaintenanceCategory::class, 'category_id'); }
}
