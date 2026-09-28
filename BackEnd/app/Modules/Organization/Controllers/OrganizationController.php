<?php
namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Modules\Organization\Repositories\Interfaces\OrganizationRepositoryInterface;
use App\Modules\Organization\Requests\StoreBranchRequest;
use App\Modules\Organization\Requests\StoreDepartmentRequest;
use App\Modules\Organization\Requests\UpdateBranchRequest;
use App\Modules\Organization\Requests\UpdateDepartmentRequest;
use App\Modules\Organization\Resources\BranchResource;
use App\Modules\Organization\Resources\DepartmentResource;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(private OrganizationRepositoryInterface $organization) {}

    /** القوائم كاملةً — تملأ القوائم المنسدلة في نماذج التحرير. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['branches' => BranchResource::collection($this->organization->branches($request->user())), 'departments' => DepartmentResource::collection($this->organization->departments($request->user()))]);
    }

    /** قائمة الأفرع كما تُعرض في الشاشة: صفحةً صفحة وقابلة للبحث. */
    public function branches(Request $request): JsonResponse
    {
        $page = $this->organization->paginateBranches($request->user(), $this->filters($request));

        return response()->json([
            'data' => BranchResource::collection($page->items()),
            'meta' => ListQuery::meta($page),
        ]);
    }

    public function departments(Request $request): JsonResponse
    {
        $page = $this->organization->paginateDepartments($request->user(), $this->filters($request));

        return response()->json([
            'data' => DepartmentResource::collection($page->items()),
            'meta' => ListQuery::meta($page),
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'search' => $request->string('search')->toString(),
            'per_page' => $request->integer('per_page'),
        ];
    }
    public function storeBranch(StoreBranchRequest $request): BranchResource { return new BranchResource($this->organization->createBranch($request->user(), $request->validated())); }
    public function updateBranch(UpdateBranchRequest $request, Branch $branch): BranchResource { return new BranchResource($this->organization->updateBranch($request->user(), $branch, $request->validated())); }
    public function destroyBranch(Request $request, Branch $branch): JsonResponse { $this->organization->deleteBranch($request->user(), $branch); return response()->json(['message'=>__('messages.branch_deleted')]); }
    public function storeDepartment(StoreDepartmentRequest $request): DepartmentResource { return new DepartmentResource($this->organization->createDepartment($request->user(), $request->validated())->load('branch:id,name')); }
    public function updateDepartment(UpdateDepartmentRequest $request, Department $department): DepartmentResource { return new DepartmentResource($this->organization->updateDepartment($request->user(), $department, $request->validated())); }
    public function destroyDepartment(Request $request, Department $department): JsonResponse { $this->organization->deleteDepartment($request->user(), $department); return response()->json(['message'=>__('messages.department_deleted')]); }
}
