<?php
namespace App\Modules\Core\Repositories\Eloquent;

use App\Models\Task;
use App\Models\User;
use App\Modules\Core\Repositories\Interfaces\CoreRepositoryInterface;
use App\Modules\Reports\Services\ReportAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class CoreRepository implements CoreRepositoryInterface
{
    /** علاقات هوية المستخدم التي تعتمدها الواجهة بعد الدخول. */
    private const PROFILE_RELATIONS = ['branch:id,name', 'department:id,name', 'office:id,name', 'roles:id,name'];

    public function __construct(private ReportAccessService $reportAccess) {}

    public function databaseReachable(): bool
    {
        try {
            DB::select('select 1');
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    public function connectionName(): string
    {
        return DB::getDefaultConnection();
    }

    public function findActiveByEmail(string $email): ?User
    {
        return User::with(self::PROFILE_RELATIONS)->where('email', $email)->where('is_active', true)->first();
    }

    public function setApiToken(User $user, ?string $hashedToken): void
    {
        $user->update(['api_token' => $hashedToken]);
    }

    public function loadProfile(User $user): User
    {
        return $user->load(self::PROFILE_RELATIONS);
    }

    public function reportsSince(User $user, Carbon $since): Collection
    {
        return $this->reports($user)->where('created_at', '>=', $since)->get(['created_at', 'status']);
    }

    public function reportStatusCounts(User $user): Collection
    {
        return $this->reports($user)->reorder()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
    }

    public function returnedReportsCount(User $user): int
    {
        return $this->reports($user)->where('status', 'returned')->count();
    }

    /** المهام التي أُنشئت أو اعتُمدت بعد التاريخ — منحنى الأسابيع يحتاج الطرفين. */
    public function tasksTouchedSince(User $user, Carbon $since): Collection
    {
        return $this->tasks($user)
            ->where(fn (Builder $query) => $query->where('created_at', '>=', $since)->orWhere('completed_at', '>=', $since))
            ->get(['created_at', 'completed_at']);
    }

    public function taskCounts(User $user): array
    {
        return [
            'total' => $this->tasks($user)->count(),
            'planned' => $this->tasks($user)->where('status', 'planned')->count(),
            'in_progress' => $this->tasks($user)->where('status', 'in_progress')->count(),
            'completed' => $this->tasks($user)->where('status', 'completed')->count(),
            'urgent' => $this->tasks($user)->where('priority', 'urgent')->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'due_this_week' => $this->tasks($user)->whereNotIn('status', ['completed', 'cancelled'])
                ->whereBetween('due_date', [today(), Carbon::today()->addDays(7)])->count(),
        ];
    }

    public function overdueTasksCount(User $user): int
    {
        return $this->tasks($user)->whereNotIn('status', ['completed', 'cancelled'])->whereDate('due_date', '<', today())->count();
    }

    private function reports(User $user): Builder
    {
        return $this->reportAccess->visibleTo($user);
    }

    /** نطاق المهام في اللوحة الرئيسية حسب دور المستخدم. */
    private function tasks(User $user): Builder
    {
        $query = Task::query();

        return match ($user->primaryRole()) {
            'general_manager', 'database_manager' => $query,
            'branch_manager' => $query->whereHas('department', fn (Builder $department) => $department->where('branch_id', $user->branch_id)),
            'department_head' => $query->where('department_id', $user->department_id),
            default => $query->where(fn (Builder $task) => $task->where('assignee_id', $user->id)->orWhere('creator_id', $user->id)),
        };
    }
}
