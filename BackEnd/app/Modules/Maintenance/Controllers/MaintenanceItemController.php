<?php

namespace App\Modules\Maintenance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceItem;
use App\Modules\Maintenance\Repositories\Interfaces\MaintenanceRepositoryInterface;
use App\Modules\Maintenance\Requests\SaveMaintenanceItemRequest;
use App\Modules\Maintenance\Services\MaintenanceInventoryService;
use App\Modules\Maintenance\Services\MaintenanceWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MaintenanceItemController extends Controller
{
    public function __construct(
        private MaintenanceRepositoryInterface $maintenance,
        private MaintenanceInventoryService $inventory,
    ) {}

    /** سجل القطع بمرشّحاته، مع ما تحتاجه الواجهة لرسم النماذج وسير العمل. */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $this->maintenance->items($this->filters($request)),
            'catalog' => $this->maintenance->catalog(),
            'transitions' => MaintenanceWorkflow::transitions(),
            'can_manage' => $request->user()->can('maintenance.manage'),
        ]);
    }

    public function show(MaintenanceItem $maintenanceItem): JsonResponse
    {
        return response()->json($this->maintenance->loadItem($maintenanceItem));
    }

    public function store(SaveMaintenanceItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        $item = $this->maintenance->transaction(function () use ($request, $data, $actor) {
            $item = $this->maintenance->createItem([
                ...$this->attributes($data),
                'image_path' => $request->hasFile('image') ? $request->file('image')->store('maintenance-items', 'public') : null,
                'created_by_id' => $actor->id,
            ]);

            if (($data['initial_quantity'] ?? 0) > 0) {
                $item = $this->inventory->receive($item, (int) $data['initial_quantity'], $actor, 'الكمية الأولى عند تسجيل القطعة', $data['initial_status'] ?? 'in_stock');
            }

            return $item;
        });

        return response()->json($this->maintenance->loadItem($item), 201);
    }

    public function update(SaveMaintenanceItemRequest $request, MaintenanceItem $maintenanceItem): JsonResponse
    {
        $data = $request->validated();
        $attributes = $this->attributes($data);

        if ($request->hasFile('image') || ! empty($data['remove_image'])) {
            if ($maintenanceItem->image_path) {
                Storage::disk('public')->delete($maintenanceItem->image_path);
            }
            $attributes['image_path'] = $request->hasFile('image') ? $request->file('image')->store('maintenance-items', 'public') : null;
        }

        return response()->json($this->maintenance->loadItem($this->maintenance->updateItem($maintenanceItem, $attributes)));
    }

    public function destroy(MaintenanceItem $maintenanceItem): JsonResponse
    {
        $this->maintenance->deleteItem($maintenanceItem);

        return response()->json(['deleted' => true]);
    }

    /** إدخال كمية جديدة من الصنف إلى المستودع. */
    public function receive(Request $request, MaintenanceItem $maintenanceItem): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = $this->inventory->receive($maintenanceItem, (int) $data['quantity'], $request->user(), $data['note'] ?? null);

        return response()->json($this->maintenance->loadItem($item));
    }

    public function move(Request $request, MaintenanceItem $maintenanceItem): JsonResponse
    {
        $data = $request->validate([
            'from_status' => ['required', Rule::in(MaintenanceWorkflow::STATUSES)],
            'to_status' => ['required', Rule::in(MaintenanceWorkflow::STATUSES)],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = $this->inventory->move($maintenanceItem, $data['from_status'], $data['to_status'], (int) $data['quantity'], $request->user(), $data['note'] ?? null);

        return response()->json($this->maintenance->loadItem($item));
    }

    /** صرف قطع خارج القسم — يُطلب سببها كي يبقى نقص الكمية مفسَّراً. */
    public function issue(Request $request, MaintenanceItem $maintenanceItem): JsonResponse
    {
        $data = $request->validate([
            'from_status' => ['required', Rule::in(MaintenanceWorkflow::STATUSES)],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['required', 'string', 'max:1000'],
        ], ['note.required' => 'اكتب لمن صُرفت القطع أو سبب إخراجها.']);

        $item = $this->inventory->issue($maintenanceItem, $data['from_status'], (int) $data['quantity'], $request->user(), $data['note']);

        return response()->json($this->maintenance->loadItem($item));
    }

    private function filters(Request $request): array
    {
        return [
            'search' => $request->string('search')->toString(),
            'category_id' => $request->integer('category_id') ?: null,
            'type_id' => $request->integer('type_id') ?: null,
            'brand_id' => $request->integer('brand_id') ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'low_stock' => $request->boolean('low_stock'),
        ];
    }

    private function attributes(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'part_number' => $this->clean($data['part_number'] ?? null),
            'category_id' => $data['category_id'] ?? null,
            'type_id' => $data['type_id'] ?? null,
            'brand_id' => $data['brand_id'] ?? null,
            'device' => $this->clean($data['device'] ?? null),
            'unit' => $this->clean($data['unit'] ?? null) ?? 'قطعة',
            'unit_price' => $data['unit_price'] ?? null,
            'min_quantity' => (int) ($data['min_quantity'] ?? 0),
            'location' => $this->clean($data['location'] ?? null),
            'notes' => $this->clean($data['notes'] ?? null),
        ];
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
