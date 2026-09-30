<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** فئة القطعة في قسم الصيانة: قطع إلكترونية، عدد يدوية، أجهزة قياس... */
class MaintenanceCategory extends Model
{
    protected $fillable = ['name', 'description'];

    public function types(): HasMany { return $this->hasMany(MaintenanceType::class, 'category_id')->orderBy('name'); }
    public function items(): HasMany { return $this->hasMany(MaintenanceItem::class, 'category_id'); }
}
