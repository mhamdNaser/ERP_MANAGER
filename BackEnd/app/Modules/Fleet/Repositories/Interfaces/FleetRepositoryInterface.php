<?php
namespace App\Modules\Fleet\Repositories\Interfaces;

use App\Models\FleetMission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

interface FleetRepositoryInterface
{
    public function forUser(int $userId): Collection;
    /** مرشّحات اللوحة: statuses، status، user_id. */
    public function filtered(array $filters): Collection;
    public function create(array $attributes): FleetMission;
    public function update(FleetMission $mission, array $attributes): FleetMission;
    public function loadRelations(FleetMission $mission): FleetMission;
    public function recordAction(FleetMission $mission, array $attributes): void;

    /** تسلسل الرقم المرجعي لمهام اليوم. */
    public function nextDailySequence(): int;
    /** تسلسل رقم الوثيقة المولَّدة لليوم. */
    public function nextDocumentSequence(): int;
    public function setDocumentFields(FleetMission $mission, array $attributes): void;

    public function findEmployee(int $userId): User;
    public function activeEmployees(): Collection;

    /** فرع الآليات وأطراف المسار — تُستعمل للصلاحيات والإشعارات. */
    public function fleetDepartmentIds(): array;
    public function fleetStaffIds(): BaseCollection;
    public function generalManagerIds(): BaseCollection;
    public function watcherIds(): BaseCollection;

    public function transaction(callable $callback): mixed;
}
