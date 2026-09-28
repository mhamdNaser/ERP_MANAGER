<?php
namespace App\Modules\Organization\Repositories\Eloquent;

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use App\Modules\Organization\Repositories\Interfaces\OrganizationRepositoryInterface;
use App\Modules\Organization\Services\OrganizationScopeService;
use App\Support\ListQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function __construct(private OrganizationScopeService $scope) {}

    public function branches(User $actor): Collection { return $this->scope->branches($actor)->withCount(['departments'])->get(); }
    public function departments(User $actor): Collection { return $this->scope->departments($actor)->with('branch:id,name')->withCount('users')->get(); }

    public function paginateBranches(User $actor, array $filters): LengthAwarePaginator
    {
        $query = $this->scope->branches($actor)->withCount('departments');

        return ListQuery::search($query, $filters['search'] ?? null, ['name', 'code'])
            ->orderBy('name')
            ->paginate(ListQuery::perPage($filters['per_page'] ?? null));
    }

    /** يبحث في اسم القسم ورمزه واسم فرعه — «أقسام فرع الشبكات» سؤالٌ طبيعي. */
    public function paginateDepartments(User $actor, array $filters): LengthAwarePaginator
    {
        $query = $this->scope->departments($actor)->with('branch:id,name')->withCount('users');

        return ListQuery::search($query, $filters['search'] ?? null, ['name', 'code', 'branch.name'])
            ->orderBy('name')
            ->paginate(ListQuery::perPage($filters['per_page'] ?? null));
    }

    public function createBranch(User $actor, array $data): Branch
    {
        if (! in_array($actor->primaryRole(), ['general_manager', 'database_manager'])) throw new AuthorizationException(__('messages.organization_forbidden'));
        return Branch::create($data);
    }

    public function updateBranch(User $actor, Branch $branch, array $data): Branch
    {
        if (! $this->scope->canManageBranch($actor, $branch)) throw new AuthorizationException(__('messages.organization_forbidden'));
        $branch->update($data); return $branch;
    }

    public function deleteBranch(User $actor, Branch $branch): bool
    {
        if (! $this->scope->canManageBranch($actor, $branch)) throw new AuthorizationException(__('messages.organization_forbidden'));
        return (bool) $branch->delete();
    }

    public function createDepartment(User $actor, array $data): Department
    {
        $department = new Department($data);
        if (! $this->scope->canManageDepartment($actor, $department)) throw new AuthorizationException(__('messages.organization_forbidden'));
        return Department::create($data);
    }

    public function updateDepartment(User $actor, Department $department, array $data): Department
    {
        if (! $this->scope->canManageDepartment($actor, $department)) throw new AuthorizationException(__('messages.organization_forbidden'));
        $target = new Department($data + ['branch_id' => $department->branch_id]);
        if (! $this->scope->canManageDepartment($actor, $target)) throw new AuthorizationException(__('messages.organization_forbidden'));
        $department->update($data); return $department->load('branch:id,name');
    }

    public function deleteDepartment(User $actor, Department $department): bool
    {
        if (! $this->scope->canManageDepartment($actor, $department)) throw new AuthorizationException(__('messages.organization_forbidden'));
        return (bool) $department->delete();
    }
}
