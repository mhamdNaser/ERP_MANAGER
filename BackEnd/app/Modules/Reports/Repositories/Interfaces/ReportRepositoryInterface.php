<?php
namespace App\Modules\Reports\Repositories\Interfaces;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface ReportRepositoryInterface
{
    public function visibleTo(User $user, ?int $limit = null): Collection;
    public function createFor(User $user, array $data): Report;
    public function updateReturned(User $user, Report $report, array $data): Report;
    public function transition(User $user, Report $report, string $action, ?string $note = null): Report;
    public function statistics(User $user): array;
}
