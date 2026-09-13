<?php
namespace App\Modules\Permissions\Repositories\Eloquent;

use App\Modules\Permissions\Repositories\Interfaces\PermissionRepositoryInterface;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function matrix(): Collection
    {
        $permissions = Permission::orderBy('name')->pluck('name');
        return Role::with('permissions')->orderBy('name')->get()->map(fn (Role $role) => [
            'id' => $role->id, 'name' => $role->name, 'permissions' => $role->permissions->pluck('name'),
            'available_permissions' => $permissions,
        ]);
    }

    public function sync(Role $role, array $permissions): Role
    {
        $role->syncPermissions($permissions);
        return $role->load('permissions');
    }
}
