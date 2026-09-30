<?php

namespace App\Modules\Maintenance\Services;

use App\Models\MaintenanceItem;
use App\Models\User;
use App\Modules\Maintenance\Repositories\Interfaces\MaintenanceRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * الكاتب الوحيد لأعمدة الكمية. كل تغيير يمر بقفل على صف الصنف ويُسجَّل
 * حركةً معه في المعاملة نفسها، فلا يختلف الرقم عن سجله أبداً.
 */
class MaintenanceInventoryService
{
    public function __construct(private MaintenanceRepositoryInterface $maintenance) {}

    /** إدخال كمية جديدة إلى المستودع — تبدأ عادةً «ضمن المخزن». */
    public function receive(MaintenanceItem $item, int $quantity, User $actor, ?string $note = null, string $status = 'in_stock'): MaintenanceItem
    {
        return $this->apply($item, $actor, 'receive', null, $status, $quantity, $note);
    }

    /** نقل عدد من القطع من حالة إلى أخرى وفق سير العمل. */
    public function move(MaintenanceItem $item, string $from, string $to, int $quantity, User $actor, ?string $note = null): MaintenanceItem
    {
        if (! MaintenanceWorkflow::canMove($from, $to)) {
            throw ValidationException::withMessages([
                'to_status' => 'لا يمكن نقل القطع من «'.MaintenanceWorkflow::label($from).'» إلى «'.MaintenanceWorkflow::label($to).'».',
            ]);
        }

        return $this->apply($item, $actor, 'move', $from, $to, $quantity, $note);
    }

    /** صرف قطع خارج القسم (تركيبها في جهاز أو تسليمها لجهة). */
    public function issue(MaintenanceItem $item, string $from, int $quantity, User $actor, ?string $note = null): MaintenanceItem
    {
        return $this->apply($item, $actor, 'issue', $from, null, $quantity, $note);
    }

    private function apply(MaintenanceItem $item, User $actor, string $kind, ?string $from, ?string $to, int $quantity, ?string $note): MaintenanceItem
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'العدد يجب أن يكون 1 على الأقل.']);
        }

        return $this->maintenance->transaction(function () use ($item, $actor, $kind, $from, $to, $quantity, $note) {
            $locked = $this->maintenance->lockItem($item->id);

            if ($from !== null) {
                $available = $locked->quantityIn($from);
                if ($available < $quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => "المتوفر في «".MaintenanceWorkflow::label($from)."» {$available} فقط.",
                    ]);
                }
                $locked->{MaintenanceWorkflow::column($from)} = $available - $quantity;
            }

            if ($to !== null) {
                $locked->{MaintenanceWorkflow::column($to)} = $locked->quantityIn($to) + $quantity;
            }

            $locked->save();
            $this->maintenance->recordMovement($locked, [
                'actor_id' => $actor->id,
                'kind' => $kind,
                'from_status' => $from,
                'to_status' => $to,
                'quantity' => $quantity,
                'note' => $note,
            ]);

            return $locked;
        });
    }
}
