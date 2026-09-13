<?php
namespace App\Modules\Tasks\Repositories\Interfaces;

use App\Models\Department;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskStageTransition;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;

interface TaskRepositoryInterface
{
    /**
     * نطاق رؤية لوحات المهام — مصدر واحد للوحة وللإحصائيات حتى لا يفترق
     * التعريفان: أي قسم يظهر في الإحصائيات هو نفسه القسم الذي تظهر لوحته.
     */
    public function departmentsFor(User $user): Collection;
    public function departmentIdsFor(User $user): BaseCollection;
    public function userSeesDepartment(User $user, int $departmentId): bool;
    public function findDepartment(int $departmentId): Department;

    /** لوحة القسم، أو كل الأقسام عند تمرير null. */
    public function boardTasks(?int $departmentId): Collection;
    public function members(?int $departmentId, array $departmentIds): Collection;
    public function isDepartmentMember(int $userId, int $departmentId): bool;
    public function findUser(?int $userId): ?User;

    public function nextPosition(int $departmentId, string $status): int;
    public function create(array $attributes): Task;
    public function update(Task $task, array $attributes): Task;
    /** بطاقة المهمة كاملة بالترتيب الذي تعيده اللوحة. */
    public function loadCard(Task $task): Task;
    public function deleteWithActivities(Task $task): void;

    public function recordActivity(array $attributes): TaskActivity;
    public function recordTransition(array $attributes): TaskStageTransition;

    public function activities(array $departmentIds, array $filters): Collection;
    public function activityActors(array $departmentIds): Collection;

    /** مصادر لوحة الإحصائيات. */
    public function tasksInRange(array $departmentIds, ?Carbon $from, ?Carbon $to): Collection;
    public function transitionsInRange(array $departmentIds, ?Carbon $from, ?Carbon $to): Collection;
    public function activeMembers(array $departmentIds): Collection;

    public function transaction(callable $callback): mixed;
}
