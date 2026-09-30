<?php

namespace App\Modules\Maintenance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceBrand;
use App\Models\MaintenanceCategory;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceType;
use App\Modules\Maintenance\Repositories\Interfaces\MaintenanceRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** الفئات والأنواع والماركات: قوائم يضيف إليها الفرع ما يستجد من قطع. */
class MaintenanceCatalogController extends Controller
{
    public function __construct(private MaintenanceRepositoryInterface $maintenance) {}

    public function index(): JsonResponse
    {
        return response()->json($this->maintenance->catalog());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:maintenance_categories,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ], ['name.unique' => 'هذه الفئة موجودة مسبقاً.']);

        return response()->json($this->maintenance->createCategory($data)->load('types'), 201);
    }

    public function updateCategory(Request $request, MaintenanceCategory $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('maintenance_categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ], ['name.unique' => 'هذه الفئة موجودة مسبقاً.']);
        $category->update($data);

        return response()->json($category->load('types'));
    }

    public function destroyCategory(MaintenanceCategory $category): JsonResponse
    {
        abort_if($category->items()->exists(), 422, 'لا يمكن حذف فئة فيها قطع. انقل قطعها إلى فئة أخرى أولاً.');
        $category->delete();

        return response()->json(['deleted' => true]);
    }

    public function storeType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:maintenance_categories,id'],
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('maintenance_types', 'name')->where('category_id', $request->integer('category_id')),
            ],
        ], ['name.unique' => 'هذا النوع موجود مسبقاً في الفئة.']);

        return response()->json($this->maintenance->createType($data), 201);
    }

    public function updateType(Request $request, MaintenanceType $type): JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('maintenance_types', 'name')->where('category_id', $type->category_id)->ignore($type->id),
            ],
        ], ['name.unique' => 'هذا النوع موجود مسبقاً في الفئة.']);
        $type->update($data);

        return response()->json($type);
    }

    public function destroyType(MaintenanceType $type): JsonResponse
    {
        abort_if(MaintenanceItem::where('type_id', $type->id)->exists(), 422, 'لا يمكن حذف نوع مستخدم في قطع مسجّلة.');
        $type->delete();

        return response()->json(['deleted' => true]);
    }

    public function storeBrand(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:maintenance_brands,name'],
        ], ['name.unique' => 'هذه الماركة موجودة مسبقاً.']);

        return response()->json($this->maintenance->createBrand($data), 201);
    }

    public function updateBrand(Request $request, MaintenanceBrand $brand): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('maintenance_brands', 'name')->ignore($brand->id)],
        ], ['name.unique' => 'هذه الماركة موجودة مسبقاً.']);
        $brand->update($data);

        return response()->json($brand);
    }

    public function destroyBrand(MaintenanceBrand $brand): JsonResponse
    {
        abort_if(MaintenanceItem::where('brand_id', $brand->id)->exists(), 422, 'لا يمكن حذف ماركة مستخدمة في قطع مسجّلة.');
        $brand->delete();

        return response()->json(['deleted' => true]);
    }
}
