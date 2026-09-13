<?php
namespace App\Modules\Organization\Repositories\Interfaces;

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface OrganizationRepositoryInterface
{
    public function branches(User $actor): Collection;
    public function departments(User $actor): Collection;
    public function createBranch(User $actor, array $data): Branch;
    public function updateBranch(User $actor, Branch $branch, array $data): Branch;
    public function deleteBranch(User $actor, Branch $branch): bool;
    public function createDepartment(User $actor, array $data): Department;
    public function updateDepartment(User $actor, Department $department, array $data): Department;
    public function deleteDepartment(User $actor, Department $department): bool;
}
