<?php
namespace App\Modules\Fleet\Repositories\Eloquent;

use App\Models\Department;
use App\Models\FleetMission;
use App\Models\User;
use App\Modules\Fleet\Repositories\Interfaces\FleetRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

class FleetRepository implements FleetRepositoryInterface
{
    /** ما تحتاجه بطاقة المهمة في اللوحة: صاحبها، ومن سجّلها، وسجل قراراتها. */
    private const RELATIONS = ['user:id,name,job_title,department_id,branch_id', 'createdBy:id,name', 'actions.actor:id,name'];

    /** يُعرَف فرع الآليات بالرمز FLEET أو بورود أحد هذه الأسماء فيه. */
    private const FLEET_KEYWORDS = ['آليات', 'الآليات', 'اليات', 'fleet', 'vehicles'];

    public function forUser(int $userId): Collection
    {
        return FleetMission::with(self::RELATIONS)->where('user_id', $userId)->latest()->get();
    }

    public function filtered(array $filters): Collection
    {
        return FleetMission::with(self::RELATIONS)
            ->when($filters['statuses'] ?? null, fn (Builder $query, array $statuses) => $query->whereIn('status', $statuses))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->latest()
            ->get();
    }

    public function create(array $attributes): FleetMission
    {
        return FleetMission::create($attributes);
    }

    public function update(FleetMission $mission, array $attributes): FleetMission
    {
        $mission->update($attributes);

        return $mission->fresh();
    }

    public function loadRelations(FleetMission $mission): FleetMission
    {
        return $mission->load(self::RELATIONS);
    }

    public function recordAction(FleetMission $mission, array $attributes): void
    {
        $mission->actions()->create($attributes);
    }

    public function nextDailySequence(): int
    {
        return FleetMission::whereDate('created_at', today())->count() + 1;
    }

    public function nextDocumentSequence(): int
    {
        return FleetMission::whereNotNull('document_number')->whereDate('updated_at', today())->count() + 1;
    }

    /** أرقام الوثيقة تُكتب خارج الحقول القابلة للتعبئة الجماعية. */
    public function setDocumentFields(FleetMission $mission, array $attributes): void
    {
        $mission->forceFill($attributes)->save();
    }

    public function findEmployee(int $userId): User
    {
        return User::findOrFail($userId);
    }

    public function activeEmployees(): Collection
    {
        return User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'job_title', 'department_id']);
    }

    public function fleetDepartmentIds(): array
    {
        return Department::query()
            ->where(function (Builder $query) {
                $query->where('code', 'FLEET');
                foreach (self::FLEET_KEYWORDS as $keyword) {
                    $query->orWhere('name', 'like', "%{$keyword}%");
                }
            })
            ->pluck('id')
            ->all();
    }

    public function fleetStaffIds(): BaseCollection
    {
        return User::where('is_active', true)->whereIn('department_id', $this->fleetDepartmentIds() ?: [0])->pluck('id');
    }

    public function generalManagerIds(): BaseCollection
    {
        return User::where('is_active', true)->where('role', 'general_manager')->pluck('id');
    }

    public function watcherIds(): BaseCollection
    {
        return User::where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereIn('department_id', $this->fleetDepartmentIds() ?: [0])
                ->orWhere('role', 'general_manager'))
            ->pluck('id');
    }

    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
