<?php

namespace App\Models;

use App\Modules\Maintenance\Services\MaintenanceWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * صنف في مستودع الصيانة. الكمية موزّعة على حالات MaintenanceWorkflow،
 * ولا تُعدَّل أعمدة qty_* إلا عبر MaintenanceInventoryService.
 */
class MaintenanceItem extends Model
{
    protected $fillable = [
        'code', 'name', 'part_number', 'category_id', 'type_id', 'brand_id',
        'device', 'unit', 'unit_price', 'min_quantity', 'location', 'notes',
        'image_path', 'import_id', 'created_by_id',
    ];

    protected $appends = ['image_url', 'quantities', 'total_quantity', 'is_low_stock'];

    protected $hidden = ['qty_in_stock', 'qty_under_maintenance', 'qty_damaged', 'qty_ready', 'qty_repaired'];

    protected function casts(): array
    {
        return ['unit_price' => 'float', 'min_quantity' => 'integer'];
    }

    public function category(): BelongsTo { return $this->belongsTo(MaintenanceCategory::class, 'category_id'); }
    public function type(): BelongsTo { return $this->belongsTo(MaintenanceType::class, 'type_id'); }
    public function brand(): BelongsTo { return $this->belongsTo(MaintenanceBrand::class, 'brand_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_id'); }
    public function movements(): HasMany { return $this->hasMany(MaintenanceMovement::class, 'item_id')->latest('id'); }

    public function quantityIn(string $status): int
    {
        return (int) $this->getAttribute(MaintenanceWorkflow::column($status));
    }

    /** الكمية في كل حالة: {in_stock: 40, under_maintenance: 5, ...}. */
    public function getQuantitiesAttribute(): array
    {
        return collect(MaintenanceWorkflow::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => $this->quantityIn($status)])
            ->all();
    }

    public function getTotalQuantityAttribute(): int
    {
        return array_sum($this->quantities);
    }

    /** الصالح للاستعمال (المخزن والجاهز وما تمت صيانته) نزل عن حدّ الطلب. */
    public function getIsLowStockAttribute(): bool
    {
        if ($this->min_quantity <= 0) {
            return false;
        }

        return collect(MaintenanceWorkflow::USABLE)->sum(fn (string $status) => $this->quantityIn($status)) < $this->min_quantity;
    }

    /**
     * رابط نسبي عمداً: الواجهة والملفات يخدمهما المضيف نفسه، وAPP_URL على
     * الخوادم بقي على عنوان الشبكة القديم فتنكسر به الروابط المطلقة.
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? '/storage/'.ltrim($this->image_path, '/') : null;
    }
}
