<?php

namespace App\Modules\Maintenance\Repositories\Eloquent;

use App\Models\MaintenanceBrand;
use App\Models\MaintenanceCategory;
use App\Models\MaintenanceImport;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceMovement;
use App\Models\MaintenanceType;
use App\Modules\Maintenance\Repositories\Interfaces\MaintenanceRepositoryInterface;
use App\Modules\Maintenance\Services\MaintenanceWorkflow;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MaintenanceRepository implements MaintenanceRepositoryInterface
{
    private const RELATIONS = ['category:id,name', 'type:id,name,category_id', 'brand:id,name'];

    private const USABLE_SUM = '(qty_in_stock + qty_ready + qty_repaired)';

    private const SEARCHABLE = ['name', 'part_number', 'device', 'code', 'location', 'notes'];

    public function items(array $filters): Collection
    {
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $status = $filters['status'] ?? null;

        return MaintenanceItem::with(self::RELATIONS)
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                foreach (self::SEARCHABLE as $column) {
                    $query->orWhereRaw("LOWER({$column}) LIKE ?", ["%{$search}%"]);
                }
            }))
            ->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->where('category_id', $id))
            ->when($filters['type_id'] ?? null, fn (Builder $query, $id) => $query->where('type_id', $id))
            ->when($filters['brand_id'] ?? null, fn (Builder $query, $id) => $query->where('brand_id', $id))
            ->when(MaintenanceWorkflow::isStatus($status), fn (Builder $query) => $query->where(MaintenanceWorkflow::column($status), '>', 0))
            ->when(! empty($filters['low_stock']), fn (Builder $query) => $query->where('min_quantity', '>', 0)->whereRaw(self::USABLE_SUM.' < min_quantity'))
            ->orderBy('category_id')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function loadItem(MaintenanceItem $item): MaintenanceItem
    {
        return $item->load([
            ...self::RELATIONS,
            'createdBy:id,name',
            'movements' => fn ($query) => $query->limit(50),
            'movements.actor:id,name',
        ]);
    }

    public function createItem(array $attributes): MaintenanceItem
    {
        $item = MaintenanceItem::create($attributes);
        $item->forceFill(['code' => 'MNT-'.str_pad((string) $item->id, 5, '0', STR_PAD_LEFT)])->save();

        return $item;
    }

    public function updateItem(MaintenanceItem $item, array $attributes): MaintenanceItem
    {
        $item->update($attributes);

        return $item->fresh();
    }

    public function deleteItem(MaintenanceItem $item): void
    {
        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }
        $item->delete();
    }

    public function lockItem(int $id): MaintenanceItem
    {
        return MaintenanceItem::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function recordMovement(MaintenanceItem $item, array $attributes): void
    {
        $item->movements()->create($attributes);
    }

    public function findMatchingItem(string $name, ?string $partNumber, ?int $categoryId, ?int $exceptImportId = null): ?MaintenanceItem
    {
        return MaintenanceItem::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when(
                $partNumber,
                fn (Builder $query) => $query->whereRaw('LOWER(part_number) = ?', [mb_strtolower($partNumber)]),
                fn (Builder $query) => $query->whereNull('part_number'),
            )
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId), fn (Builder $query) => $query->whereNull('category_id'))
            ->when($exceptImportId, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('import_id')->orWhere('import_id', '!=', $exceptImportId)))
            ->orderBy('id')
            ->first();
    }

    public function catalog(): array
    {
        return [
            'categories' => MaintenanceCategory::with('types:id,category_id,name')->withCount('items')->orderBy('name')->get(),
            'brands' => MaintenanceBrand::orderBy('name')->get(['id', 'name']),
            // الأجهزة والوحدات قيم حرّة، تُقترح من المسجَّل لتبقى الكتابة موحّدة.
            'devices' => MaintenanceItem::whereNotNull('device')->distinct()->orderBy('device')->pluck('device'),
            'units' => MaintenanceItem::distinct()->orderBy('unit')->pluck('unit'),
        ];
    }

    public function createCategory(array $attributes): MaintenanceCategory
    {
        return MaintenanceCategory::create($attributes);
    }

    public function createType(array $attributes): MaintenanceType
    {
        return MaintenanceType::create($attributes);
    }

    public function createBrand(array $attributes): MaintenanceBrand
    {
        return MaintenanceBrand::create($attributes);
    }

    public function categoryNamed(string $name): MaintenanceCategory
    {
        return MaintenanceCategory::firstOrCreate(['name' => $name]);
    }

    public function categoryIdNamed(string $name): ?int
    {
        return MaintenanceCategory::where('name', $name)->value('id');
    }

    public function typeNamesOf(int $categoryId): array
    {
        // الأطول أولاً كي يغلب «ترانزستور قدرة» على «ترانزستور» إن وُجدا.
        return MaintenanceType::where('category_id', $categoryId)->pluck('name')
            ->sortByDesc(fn (string $name) => mb_strlen($name))->values()->all();
    }

    public function typeNamed(int $categoryId, string $name): MaintenanceType
    {
        return MaintenanceType::firstOrCreate(['category_id' => $categoryId, 'name' => $name]);
    }

    public function brandNamed(string $name): MaintenanceBrand
    {
        // الماركة نفسها قد تُكتب goot وGOOT في ملفين، فتُطابَق دون حساسية للحالة.
        return MaintenanceBrand::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? MaintenanceBrand::create(['name' => $name]);
    }

    public function movementsSince(CarbonInterface $since): Collection
    {
        return MaintenanceMovement::where('created_at', '>=', $since)
            ->get(['id', 'item_id', 'kind', 'from_status', 'to_status', 'quantity', 'created_at']);
    }

    public function recentMovements(int $limit): Collection
    {
        return MaintenanceMovement::with(['item:id,name,code,part_number,unit', 'actor:id,name'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function imports(): Collection
    {
        return MaintenanceImport::with(['category:id,name', 'actor:id,name'])->latest('id')->limit(30)->get();
    }

    public function createImport(array $attributes): MaintenanceImport
    {
        return MaintenanceImport::create($attributes);
    }

    /**
     * يحذف الملف المرفوع وسجله فقط. القطع المستوردة منه وكمياتها وحركاتها باقية؛
     * يُفَكّ ارتباطها بالملف لا أكثر.
     */
    public function deleteImport(MaintenanceImport $import): void
    {
        DB::transaction(function () use ($import) {
            MaintenanceItem::where('import_id', $import->id)->update(['import_id' => null]);
            $import->delete();
        });
        Storage::disk('public')->delete($import->file_path);
    }

    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
