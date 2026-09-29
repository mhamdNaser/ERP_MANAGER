<?php
namespace App\Modules\Tasks\Repositories\Eloquent;

use App\Models\Department;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskStageTransition;
use App\Models\User;
use App\Modules\Organization\Services\OrganizationScopeService;
use App\Modules\Tasks\Repositories\Interfaces\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

class TaskRepository implements TaskRepositoryInterface
{
    /** علاقات بطاقة المهمة بالترتيب نفسه الذي تعيده اللوحة (الأحدث أولًا). */
    private const CARD_RELATIONS = [
        'department:id,name',
        'creator:id,name',
        'assignee:id,name,job_title',
        'communicationUser:id,name,job_title',
        'files.uploader:id,name,role',
    ];

    public function __construct(private OrganizationScopeService $scope) {}

    public function departmentsFor(User $user): Collection
    {
        return $this->departmentScope($user)->with('branch:id,name')->orderBy('name')->get();
    }

    public function departmentIdsFor(User $user): BaseCollection
    {
        return $this->departmentScope($user)->pluck('id');
    }

    public function userSeesDepartment(User $user, int $departmentId): bool
    {
        return $this->departmentScope($user)->whereKey($departmentId)->exists();
    }

    public function findDepartment(int $departmentId): Department
    {
        return Department::findOrFail($departmentId);
    }

    public function boardTasks(?int $departmentId): Collection
    {
        return Task::with($this->cardRelations())
            ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();
    }

    public function members(?int $departmentId, array $departmentIds): BaseCollection
    {
        return User::when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->when(! $departmentId, fn (Builder $query) => $query->whereIn('department_id', $departmentIds))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'job_title'])
            // تُعلَّم صفة التواصل هنا مرةً واحدة بدل استعلام لكل عضو في الواجهة.
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'job_title' => $member->job_title,
                'is_communication_officer' => $member->isCommunicationOfficer(),
            ]);
    }

    /** هل في القسم موظف تواصل معيَّن أصلاً؟ */
    public function departmentHasCommunicationOfficer(int $departmentId): bool
    {
        return User::where('department_id', $departmentId)
            ->where('is_active', true)
            ->permission(User::COMMUNICATION_PERMISSION)
            ->exists();
    }

    public function isCommunicationOfficer(int $userId): bool
    {
        return User::whereKey($userId)->permission(User::COMMUNICATION_PERMISSION)->exists();
    }

    public function isDepartmentMember(int $userId, int $departmentId): bool
    {
        return User::whereKey($userId)->where('department_id', $departmentId)->exists();
    }

    public function findUser(?int $userId): ?User
    {
        return $userId ? User::find($userId) : null;
    }

    public function nextPosition(int $departmentId, string $status): int
    {
        return (Task::where('department_id', $departmentId)->where('status', $status)->max('position') ?? -1) + 1;
    }

    public function create(array $attributes): Task
    {
        return Task::create($attributes);
    }

    public function update(Task $task, array $attributes): Task
    {
        $task->update($attributes);

        return $task;
    }

    public function loadCard(Task $task): Task
    {
        return $task->load($this->cardRelations());
    }

    public function deleteWithActivities(Task $task): void
    {
        $this->transaction(function () use ($task): void {
            $task->activities()->delete();
            $task->delete();
        });
    }

    public function recordActivity(array $attributes): TaskActivity
    {
        return TaskActivity::create($attributes);
    }

    public function recordTransition(array $attributes): TaskStageTransition
    {
        return TaskStageTransition::create($attributes);
    }

    public function activities(array $departmentIds, array $filters): Collection
    {
        return TaskActivity::with(['actor:id,name,job_title', 'department:id,name'])
            ->whereIn('department_id', $departmentIds)
            ->when($filters['actor_id'] ?? null, fn (Builder $query, $actorId) => $query->where('actor_id', $actorId))
            ->when($filters['action'] ?? null, fn (Builder $query, $action) => $query->where('action', $action))
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->limit(300)
            ->get();
    }

    public function activityActors(array $departmentIds): Collection
    {
        return TaskActivity::with('actor:id,name,job_title')
            ->whereIn('department_id', $departmentIds)
            ->whereNotNull('actor_id')
            ->get();
    }

    public function tasksInRange(array $departmentIds, ?Carbon $from, ?Carbon $to): Collection
    {
        return Task::with(['assignee:id,name,job_title', 'communicationUser:id,name,job_title'])
            ->whereIn('department_id', $departmentIds)
            ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('created_at', '<=', $to))
            ->get();
    }

    public function transitionsInRange(array $departmentIds, ?Carbon $from, ?Carbon $to): Collection
    {
        return TaskStageTransition::whereIn('department_id', $departmentIds)
            ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('created_at', '<=', $to))
            ->get();
    }

    public function activeMembers(array $departmentIds): Collection
    {
        return User::whereIn('department_id', $departmentIds)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'job_title']);
    }

    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    /** السجل والانتقالات تُحمَّل بالأحدث أولًا حتى لا يختلف ترتيبها بعد الحفظ. */
    private function cardRelations(): array
    {
        return [
            ...self::CARD_RELATIONS,
            'activities' => fn ($activities) => $activities->with('actor:id,name,job_title')->latest(),
            'transitions' => fn ($transitions) => $transitions->with('actor:id,name,job_title')->orderByDesc('id'),
        ];
    }

    /** رئيس القسم وما فوقه يرى نطاقه التنظيمي، وغيرهم قسمه وحده. */
    private function departmentScope(User $user): Builder
    {
        if (in_array($user->primaryRole(), ['general_manager', 'database_manager', 'branch_manager', 'department_head'])) {
            return $this->scope->departments($user);
        }

        return Department::whereKey($user->department_id);
    }
}
