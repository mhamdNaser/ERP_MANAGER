<?php
namespace App\Modules\Hr\Repositories\Eloquent;

use App\Models\Department;
use App\Models\HrLeaveBalance;
use App\Models\HrRequest;
use App\Models\User;
use App\Modules\Hr\Repositories\Interfaces\HrRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

class HrRepository implements HrRepositoryInterface
{
    /** ما يحتاجه سطر الطلب في اللوحة: صاحبه، ومن سجّله، وسجل قراراته. */
    private const RELATIONS = ['user:id,name,job_title,department_id,branch_id', 'createdBy:id,name', 'actions.actor:id,name'];

    /** يُعرَف قسم الموارد البشرية بالرمز HR أو بورود أحد هذه الأسماء فيه. */
    private const HR_KEYWORDS = ['موارد بشرية', 'الموارد البشرية', 'human resources'];

    public function forUser(int $userId): Collection
    {
        return HrRequest::with(self::RELATIONS)->where('user_id', $userId)->latest()->get();
    }

    public function filtered(array $filters): Collection
    {
        return HrRequest::with(self::RELATIONS)
            ->when($filters['statuses'] ?? null, fn (Builder $query, array $statuses) => $query->whereIn('status', $statuses))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->latest()
            ->get();
    }

    public function create(array $attributes): HrRequest
    {
        return HrRequest::create($attributes);
    }

    public function update(HrRequest $request, array $attributes): HrRequest
    {
        $request->update($attributes);

        return $request->fresh();
    }

    public function loadRelations(HrRequest $request): HrRequest
    {
        return $request->load(self::RELATIONS);
    }

    public function recordAction(HrRequest $request, array $attributes): void
    {
        $request->actions()->create($attributes);
    }

    public function nextDailySequence(): int
    {
        return HrRequest::whereDate('created_at', today())->count() + 1;
    }

    public function nextDocumentSequence(): int
    {
        return HrRequest::whereNotNull('document_number')->whereDate('updated_at', today())->count() + 1;
    }

    /** أرقام الوثيقة تُكتب خارج الحقول القابلة للتعبئة الجماعية. */
    public function setDocumentFields(HrRequest $request, array $attributes): void
    {
        $request->forceFill($attributes)->save();
    }

    public function findEmployee(int $userId): User
    {
        return User::findOrFail($userId);
    }

    public function activeEmployees(): Collection
    {
        return User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'job_title', 'department_id']);
    }

    public function hrDepartmentIds(): array
    {
        return Department::query()
            ->where(function (Builder $query) {
                $query->where('code', 'HR');
                foreach (self::HR_KEYWORDS as $keyword) {
                    $query->orWhere('name', 'like', "%{$keyword}%");
                }
            })
            ->pluck('id')
            ->all();
    }

    public function hrStaffIds(): BaseCollection
    {
        return User::where('is_active', true)->whereIn('department_id', $this->hrDepartmentIds() ?: [0])->pluck('id');
    }

    public function generalManagerIds(): BaseCollection
    {
        return User::where('is_active', true)->where('role', 'general_manager')->pluck('id');
    }

    public function watcherIds(): BaseCollection
    {
        return User::where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereIn('department_id', $this->hrDepartmentIds() ?: [0])
                ->orWhere('role', 'general_manager'))
            ->pluck('id');
    }

    public function balanceFor(int $userId, int $year): HrLeaveBalance
    {
        return HrLeaveBalance::firstOrCreate(
            ['user_id' => $userId, 'year' => $year],
            ['annual_entitlement' => 30, 'used_days' => 0],
        );
    }

    public function updateBalance(HrLeaveBalance $balance, array $attributes): HrLeaveBalance
    {
        $balance->update($attributes);

        return $balance->fresh()->load('user:id,name,job_title');
    }

    public function addUsedDays(HrLeaveBalance $balance, float $days): void
    {
        $balance->increment('used_days', $days);
    }

    public function balancesForYear(int $year): Collection
    {
        return HrLeaveBalance::with('user:id,name,job_title')->where('year', $year)->get();
    }

    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
