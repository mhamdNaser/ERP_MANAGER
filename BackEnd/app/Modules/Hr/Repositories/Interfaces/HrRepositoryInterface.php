<?php
namespace App\Modules\Hr\Repositories\Interfaces;

use App\Models\HrLeaveBalance;
use App\Models\HrRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

interface HrRepositoryInterface
{
    public function forUser(int $userId): Collection;
    /** مرشّحات اللوحة: statuses، type، status، user_id. */
    public function filtered(array $filters): Collection;
    public function create(array $attributes): HrRequest;
    public function update(HrRequest $request, array $attributes): HrRequest;
    public function loadRelations(HrRequest $request): HrRequest;
    public function recordAction(HrRequest $request, array $attributes): void;

    /** تسلسل الرقم المرجعي لطلبات اليوم. */
    public function nextDailySequence(): int;
    /** تسلسل رقم الوثيقة المولَّدة لليوم. */
    public function nextDocumentSequence(): int;
    public function setDocumentFields(HrRequest $request, array $attributes): void;

    public function findEmployee(int $userId): User;
    public function activeEmployees(): Collection;

    /** قسم الموارد البشرية وأطراف المسار — تُستعمل للصلاحيات والإشعارات. */
    public function hrDepartmentIds(): array;
    public function hrStaffIds(): BaseCollection;
    public function generalManagerIds(): BaseCollection;
    public function watcherIds(): BaseCollection;

    public function balanceFor(int $userId, int $year): HrLeaveBalance;
    public function updateBalance(HrLeaveBalance $balance, array $attributes): HrLeaveBalance;
    public function addUsedDays(HrLeaveBalance $balance, float $days): void;
    public function balancesForYear(int $year): Collection;

    public function transaction(callable $callback): mixed;
}
