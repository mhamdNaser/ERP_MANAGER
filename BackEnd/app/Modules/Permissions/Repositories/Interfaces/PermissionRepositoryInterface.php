<?php
namespace App\Modules\Permissions\Repositories\Interfaces;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

interface PermissionRepositoryInterface
{
    public function matrix(): Collection;
    public function sync(Role $role, array $permissions): Role;
}
