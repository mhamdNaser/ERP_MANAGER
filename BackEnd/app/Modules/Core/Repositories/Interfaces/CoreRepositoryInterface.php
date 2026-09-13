<?php
namespace App\Modules\Core\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface CoreRepositoryInterface
{
    /** فحص الاتصال بالقاعدة لنقطة الصحة. */
    public function databaseReachable(): bool;
    public function connectionName(): string;

    public function findActiveByEmail(string $email): ?User;
    public function setApiToken(User $user, ?string $hashedToken): void;
    public function loadProfile(User $user): User;

    /** أرقام لوحة التحليلات: نتائج جاهزة، لا بانيَ استعلام يخرج من المستودع. */
    public function reportsSince(User $user, Carbon $since): Collection;
    public function reportStatusCounts(User $user): Collection;
    public function returnedReportsCount(User $user): int;
    public function tasksTouchedSince(User $user, Carbon $since): Collection;
    public function taskCounts(User $user): array;
    public function overdueTasksCount(User $user): int;
}
