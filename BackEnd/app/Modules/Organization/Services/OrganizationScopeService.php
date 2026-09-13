<?php
namespace App\Modules\Organization\Services;

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OrganizationScopeService
{
    public function employees(User $actor): Builder
    {
        return match ($actor->primaryRole()) {
            'general_manager', 'database_manager' => User::query(),
            'branch_manager' => User::where('branch_id', $actor->branch_id),
            'department_head' => User::where('department_id', $actor->department_id),
            default => User::whereKey($actor->id),
        };
    }

    public function branches(User $actor): Builder
    {
        return in_array($actor->primaryRole(), ['general_manager', 'database_manager'])
            ? Branch::query()
            : Branch::whereKey($actor->branch_id);
    }

    public function departments(User $actor): Builder
    {
        return match ($actor->primaryRole()) {
            'general_manager', 'database_manager' => Department::query(),
            'branch_manager' => Department::where('branch_id', $actor->branch_id),
            'department_head' => Department::whereKey($actor->department_id),
            default => Department::whereRaw('1 = 0'),
        };
    }

    public function canManageBranch(User $actor, Branch $branch): bool
    {
        return in_array($actor->primaryRole(), ['general_manager', 'database_manager']);
    }

    public function canManageDepartment(User $actor, Department $department): bool
    {
        return in_array($actor->primaryRole(), ['general_manager', 'database_manager'])
            || ($actor->primaryRole() === 'branch_manager' && $department->branch_id === $actor->branch_id);
    }

    public function canManageEmployee(User $actor, User $employee): bool
    {
        return match ($actor->primaryRole()) {
            'general_manager', 'database_manager' => true,
            'branch_manager' => $employee->branch_id === $actor->branch_id,
            'department_head' => $employee->department_id === $actor->department_id,
            default => false,
        };
    }

    public function canAssignRole(User $actor, string $role): bool
    {
        return match ($actor->primaryRole()) {
            'general_manager', 'database_manager' => true,
            'branch_manager' => in_array($role, ['employee', 'technician', 'department_head']),
            default => false,
        };
    }
}
