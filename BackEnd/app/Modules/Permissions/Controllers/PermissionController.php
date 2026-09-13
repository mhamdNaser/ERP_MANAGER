<?php
namespace App\Modules\Permissions\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Permissions\Repositories\Interfaces\PermissionRepositoryInterface;
use App\Modules\Permissions\Requests\SyncRolePermissionsRequest;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function __construct(private PermissionRepositoryInterface $permissions) {}
    public function index(): JsonResponse { return response()->json($this->permissions->matrix()); }
    public function update(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        return response()->json($this->permissions->sync($role, $request->validated('permissions')));
    }
}
