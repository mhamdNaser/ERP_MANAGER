<?php
namespace App\Modules\Employees\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface EmployeeRepositoryInterface
{
    public function all(User $actor): Collection;
    public function create(User $actor, array $data): User;
    public function update(User $actor, User $user, array $data): User;
    public function updateOwnDetails(User $user, array $data): User;
    public function resetPassword(User $actor, User $user, string $password): User;
    public function updatePermissions(User $actor, User $user, array $permissions): User;
    public function delete(User $actor, User $user): bool;
}
