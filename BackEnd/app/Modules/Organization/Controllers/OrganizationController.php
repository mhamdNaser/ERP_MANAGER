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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(private OrganizationRepositoryInterface $organization) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['branches' => BranchResource::collection($this->organization->branches($request->user())), 'departments' => DepartmentResource::collection($this->organization->departments($request->user()))]);
    }
    public function storeBranch(StoreBranchRequest $request): BranchResource { return new BranchResource($this->organization->createBranch($request->user(), $request->validated())); }
    public function updateBranch(UpdateBranchRequest $request, Branch $branch): BranchResource { return new BranchResource($this->organization->updateBranch($request->user(), $branch, $request->validated())); }
    public function destroyBranch(Request $request, Branch $branch): JsonResponse { $this->organization->deleteBranch($request->user(), $branch); return response()->json(['message'=>__('messages.branch_deleted')]); }
    public function storeDepartment(StoreDepartmentRequest $request): DepartmentResource { return new DepartmentResource($this->organization->createDepartment($request->user(), $request->validated())->load('branch:id,name')); }
    public function updateDepartment(UpdateDepartmentRequest $request, Department $department): DepartmentResource { return new DepartmentResource($this->organization->updateDepartment($request->user(), $department, $request->validated())); }
    public function destroyDepartment(Request $request, Department $department): JsonResponse { $this->organization->deleteDepartment($request->user(), $department); return response()->json(['message'=>__('messages.department_deleted')]); }
}
