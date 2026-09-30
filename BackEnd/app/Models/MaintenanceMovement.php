<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * حركة على كمية صنف: إدخال إلى المستودع (receive)، أو نقل بين حالتين (move)،
 * أو صرف خارج القسم (issue). كل رقم في أعمدة qty_* يفسّره مجموع حركاته.
 */
class MaintenanceMovement extends Model
{
    protected $fillable = ['item_id', 'actor_id', 'kind', 'from_status', 'to_status', 'quantity', 'note'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function item(): BelongsTo { return $this->belongsTo(MaintenanceItem::class, 'item_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
