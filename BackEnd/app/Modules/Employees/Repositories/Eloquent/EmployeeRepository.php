<?php
namespace App\Modules\Employees\Repositories\Eloquent;

use App\Models\User;
use App\Modules\Employees\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use App\Modules\Organization\Services\OrganizationScopeService;
use Illuminate\Auth\Access\AuthorizationException;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    public function __construct(private OrganizationScopeService $scope) {}

    /** العلاقات التي يحتاجها UserResource — واحدة للقائمة الكاملة وللمرقَّمة. */
    private const RELATIONS = ['branch:id,name', 'department:id,name', 'office:id,name', 'roles:id,name', 'address', 'familyDetails', 'personalDetails'];

    public function all(User $actor): Collection
    {
        return $this->scope->employees($actor)->with(self::RELATIONS)->latest()->get();
    }

    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        $query = $this->scope->employees($actor)->with(self::RELATIONS);

        $query = ListQuery::search($query, $filters['search'] ?? null, ['name', 'email', 'job_title', 'employee_number']);

        // الفلاتر تُضيّق النطاق ولا توسّعه: نطاق المستخدم مطبَّق سلفاً أعلاه.
        $query->when($filters['branch_id'] ?? null, fn ($q, $value) => $q->where('branch_id', $value))
            ->when($filters['department_id'] ?? null, fn ($q, $value) => $q->where('department_id', $value))
            ->when($filters['office_id'] ?? null, fn ($q, $value) => $q->where('office_id', $value))
            ->when($filters['role'] ?? null, fn ($q, $value) => $q->where('role', $value))
            ->when($filters['employment_type'] ?? null, fn ($q, $value) => $q->where('employment_type', $value));

        // الحالة قيمتها منطقية، فـwhen لا تصلح لها: false تُسقط الشرط.
        if (($filters['status'] ?? null) === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->latest()->paginate(ListQuery::perPage($filters['per_page'] ?? null));
    }

    public function create(User $actor, array $data): User
    {
        $details = $this->extractDetails($data);
        $probe = new User($data);
        if (! $this->scope->canManageEmployee($actor, $probe) || ! $this->scope->canAssignRole($actor, $data['role'])) throw new AuthorizationException(__('messages.organization_forbidden'));
        $role = $data['role']; $data['password'] = Hash::make($data['password']);
        $data['employment_type'] = $data['employment_type'] ?? 'contract';
        $user = User::create($data); $user->syncRoles([$role]);
        $this->syncDetails($user, $details);
        return $this->loadUser($user);
    }

    public function update(User $actor, User $user, array $data): User
    {
        $details = $this->extractDetails($data);
        if (! $this->scope->canManageEmployee($actor, $user)) throw new AuthorizationException(__('messages.organization_forbidden'));
        if (isset($data['role']) && ! $this->scope->canAssignRole($actor, $data['role'])) throw new AuthorizationException(__('messages.organization_forbidden'));
        if ($actor->primaryRole() === 'department_head' && array_intersect(array_keys($data), ['role', 'branch_id', 'department_id', 'is_active'])) throw new AuthorizationException(__('messages.organization_forbidden'));
        if (!isset($data['employment_type'])) {
            $data['employment_type'] = $user->employment_type;
        }
        $targetBranch = $data['branch_id'] ?? $user->branch_id; $targetDepartment = $data['department_id'] ?? $user->department_id;
        $probe = new User(['branch_id' => $targetBranch, 'department_id' => $targetDepartment]);
        if (! $this->scope->canManageEmployee($actor, $probe)) throw new AuthorizationException(__('messages.organization_forbidden'));
        $role = $data['role'] ?? null; $user->update($data);
        if ($role) $user->syncRoles([$role]);
        $this->syncDetails($user, $details);
        return $this->loadUser($user);
    }

    /** تعيين كلمة مرور جديدة ضمن نطاق صلاحية المدير، دون التحقق من القديمة. */
    public function resetPassword(User $actor, User $user, string $password): User
    {
        if (! $this->scope->canManageEmployee($actor, $user)) throw new AuthorizationException(__('messages.organization_forbidden'));

        $user->forceFill(['password' => Hash::make($password)])->save();

        return $this->loadUser($user);
    }

    /** ضبط الصلاحيات الممنوحة للموظف مباشرة، بالإضافة إلى صلاحيات دوره. */
    public function updatePermissions(User $actor, User $user, array $permissions): User
    {
        if (! $this->scope->canManageEmployee($actor, $user)) throw new AuthorizationException(__('messages.organization_forbidden'));

        $user->syncPermissions($permissions);

        return $this->loadUser($user);
    }

    public function delete(User $actor, User $user): bool
    {
        if (! $this->scope->canManageEmployee($actor, $user)) throw new AuthorizationException(__('messages.organization_forbidden'));
        return (bool) $user->delete();
    }

    public function updateOwnDetails(User $user, array $data): User
    {
        $this->syncDetails($user, $data);
        return $this->loadUser($user);
    }

    private function extractDetails(array &$data): array
    {
        $details = [];
        foreach (['address', 'family_details', 'personal_details'] as $key) {
            if (array_key_exists($key, $data)) { $details[$key] = $data[$key]; unset($data[$key]); }
        }
        return $details;
    }

    private function syncDetails(User $user, array $details): void
    {
        $relations = ['address'=>'address','family_details'=>'familyDetails','personal_details'=>'personalDetails'];
        foreach ($relations as $key => $relation) if (isset($details[$key])) $user->{$relation}()->updateOrCreate(['user_id'=>$user->id], $details[$key]);
    }

    private function loadUser(User $user): User
    {
        return $user->load(['branch:id,name', 'department:id,name', 'office:id,name', 'roles:id,name', 'address', 'familyDetails', 'personalDetails']);
    }
}
