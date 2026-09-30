<?php

namespace App\Modules\Maintenance\Services;

use App\Models\MaintenanceItem;
use App\Modules\Maintenance\Repositories\Interfaces\MaintenanceRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * أرقام تبويب الصيانة: أين القطع الآن (توزيعها على الحالات والفئات)،
 * وكيف تحرّكت (مسارات النقل وحصيلة الأسابيع الأخيرة).
 */
class MaintenanceStatisticsService
{
    public const FLOW_DAYS = 30;

    public const WEEKS = 8;

    public function __construct(private MaintenanceRepositoryInterface $maintenance) {}

    public function build(): array
    {
        $items = $this->maintenance->items([]);
        $movements = $this->maintenance->movementsSince(now()->startOfWeek()->subWeeks(self::WEEKS - 1));
        $recentFlow = $movements->where('created_at', '>=', now()->subDays(self::FLOW_DAYS));

        return [
            'totals' => $this->totals($items),
            'by_status' => $this->byStatus($items),
            'by_category' => $this->byCategory($items),
            'flow' => $this->flow($recentFlow),
            'flow_days' => self::FLOW_DAYS,
            'repair_rate' => $this->repairRate($recentFlow),
            'weekly' => $this->weekly($movements),
            'recent' => $this->maintenance->recentMovements(12),
            'transitions' => MaintenanceWorkflow::transitions(),
        ];
    }

    private function totals(Collection $items): array
    {
        return [
            'items' => $items->count(),
            'units' => $items->sum('total_quantity'),
            'value' => round($items->sum(fn (MaintenanceItem $item) => $item->total_quantity * ($item->unit_price ?? 0)), 2),
            'low_stock' => $items->where('is_low_stock', true)->count(),
            'categories' => $items->pluck('category_id')->filter()->unique()->count(),
        ];
    }

    private function byStatus(Collection $items): array
    {
        return collect(MaintenanceWorkflow::STATUSES)->map(fn (string $status) => [
            'status' => $status,
            'units' => $items->sum(fn (MaintenanceItem $item) => $item->quantityIn($status)),
            'items' => $items->filter(fn (MaintenanceItem $item) => $item->quantityIn($status) > 0)->count(),
            'value' => round($items->sum(fn (MaintenanceItem $item) => $item->quantityIn($status) * ($item->unit_price ?? 0)), 2),
        ])->all();
    }

    private function byCategory(Collection $items): array
    {
        return $items->groupBy(fn (MaintenanceItem $item) => $item->category_id ?? 0)
            ->map(fn (Collection $group) => [
                'id' => $group->first()->category_id,
                'name' => $group->first()->category?->name,
                'items' => $group->count(),
                'units' => $group->sum('total_quantity'),
                'value' => round($group->sum(fn (MaintenanceItem $item) => $item->total_quantity * ($item->unit_price ?? 0)), 2),
                'quantities' => collect(MaintenanceWorkflow::STATUSES)
                    ->mapWithKeys(fn (string $status) => [$status => $group->sum(fn (MaintenanceItem $item) => $item->quantityIn($status))])
                    ->all(),
            ])
            ->sortByDesc('units')
            ->values()
            ->all();
    }

    /** مجموع ما عبر كل مسار خلال المدة، ومنه الإدخال (من لا شيء) والصرف (إلى لا شيء). */
    private function flow(Collection $movements): array
    {
        return $movements->groupBy(fn ($movement) => ($movement->from_status ?? 'in').'>'.($movement->to_status ?? 'out'))
            ->map(fn (Collection $group) => [
                'from' => $group->first()->from_status,
                'to' => $group->first()->to_status,
                'kind' => $group->first()->kind,
                'quantity' => (int) $group->sum('quantity'),
                'count' => $group->count(),
            ])
            ->sortByDesc('quantity')
            ->values()
            ->all();
    }

    /** من القطع التي خرجت من الصيانة: كم منها أُصلح مقابل ما ثبت تلفه. */
    private function repairRate(Collection $movements): ?float
    {
        $outOfRepair = $movements->where('from_status', 'under_maintenance');
        $repaired = $outOfRepair->where('to_status', 'repaired')->sum('quantity');
        $damaged = $outOfRepair->where('to_status', 'damaged')->sum('quantity');

        return $repaired + $damaged > 0 ? round($repaired / ($repaired + $damaged) * 100, 1) : null;
    }

    private function weekly(Collection $movements): array
    {
        $start = now()->startOfWeek()->subWeeks(self::WEEKS - 1);

        return collect(range(0, self::WEEKS - 1))->map(function (int $offset) use ($start, $movements) {
            $from = $start->copy()->addWeeks($offset);
            $to = $from->copy()->addWeek();
            $week = $movements->filter(fn ($movement) => $movement->created_at >= $from && $movement->created_at < $to);

            return [
                'key' => $from->toDateString(),
                'label' => $from->format('m/d'),
                'received' => (int) $week->where('kind', 'receive')->sum('quantity'),
                'to_maintenance' => (int) $week->where('kind', 'move')->where('to_status', 'under_maintenance')->sum('quantity'),
                'repaired' => (int) $week->where('to_status', 'repaired')->sum('quantity'),
                'damaged' => (int) $week->where('kind', 'move')->where('to_status', 'damaged')->sum('quantity'),
                'issued' => (int) $week->where('kind', 'issue')->sum('quantity'),
            ];
        })->all();
    }
}
