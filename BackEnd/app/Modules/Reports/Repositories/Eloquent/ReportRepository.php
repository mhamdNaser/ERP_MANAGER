<?php
namespace App\Modules\Reports\Repositories\Eloquent;

use App\Models\CndNotification;
use App\Models\Report;
use App\Models\ReportAction;
use App\Models\User;
use App\Modules\Reports\Repositories\Interfaces\ReportRepositoryInterface;
use App\Modules\Reports\Services\ReportAccessService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportRepository implements ReportRepositoryInterface
{
    public function __construct(private ReportAccessService $access) {}

    public function visibleTo(User $user, ?int $limit = null): Collection
    {
        $query = $this->access->visibleTo($user);
        return ($limit ? $query->limit($limit) : $query)->get();
    }

    public function statistics(User $user): array
    {
        $visible = $this->access->visibleTo($user);
        return [
            'total' => (clone $visible)->count(),
            'pending' => (clone $visible)->whereIn('status', ['submitted', 'department_review', 'branch_review', 'general_review'])->count(),
            'returned' => (clone $visible)->where('status', 'returned')->count(),
            'completed' => (clone $visible)->where('status', 'approved')->count(),
        ];
    }

    public function createFor(User $user, array $data): Report
    {
        return Report::create($data + ['employee_id' => $user->id, 'branch_id' => $user->branch_id, 'department_id' => $user->department_id, 'status' => 'draft']);
    }

    public function updateReturned(User $user, Report $report, array $data): Report
    {
        if ($user->primaryRole() !== 'database_manager' && ($report->employee_id !== $user->id || $report->status !== 'returned')) {
            throw ValidationException::withMessages(['report' => __('messages.report_forbidden')]);
        }

        DB::transaction(function () use ($report, $user, $data) {
            $from = $report->status;
            $report->update($data);
            ReportAction::create([
                'report_id' => $report->id, 'actor_id' => $user->id, 'action' => $from === 'returned' ? 'updated_returned' : 'updated',
                'from_status' => $from, 'to_status' => $report->status,
            ]);
        });

        return $report->fresh()->load(['employee', 'branch', 'department', 'actions']);
    }

    public function transition(User $user, Report $report, string $action, ?string $note = null): Report
    {
        if (! $this->access->canManage($user, $report)) throw ValidationException::withMessages(['report' => __('messages.report_forbidden')]);
        $this->ensureAllowedTransition($user, $report, $action, $note);

        $target = match ($action) { 'submit' => 'department_review', 'forward_branch' => 'branch_review', 'forward_general' => 'general_review', 'return' => 'returned', 'approve' => 'approved' };
        $role = match ($target) { 'department_review' => 'department_head', 'branch_review' => 'branch_manager', 'general_review' => 'general_manager', default => null };
        DB::transaction(function () use ($report, $user, $action, $note, $target, $role) {
            $from = $report->status;
            $report->update(['status' => $target, 'current_reviewer_role' => $role, 'submitted_at' => $report->submitted_at ?? now()]);
            ReportAction::create(['report_id' => $report->id, 'actor_id' => $user->id, 'action' => $action, 'from_status' => $from, 'to_status' => $target, 'note' => $note]);
            if ($action === 'return') {
                Cache::put($this->returnNoteCacheKey($report), $note, now()->addDays(14));
            }
            if ($action === 'submit') {
                Cache::forget($this->returnNoteCacheKey($report));
            }
            $recipients = $target === 'returned'
                ? User::whereKey($report->employee_id)
                : ($role ? User::role($role)
                    ->when($role === 'department_head', fn ($q) => $q->where('department_id', $report->department_id))
                    ->when($role === 'branch_manager', fn ($q) => $q->where('branch_id', $report->branch_id))
                    : null);
            $recipients?->get()->each(fn ($recipient) => CndNotification::create(['user_id' => $recipient->id, 'report_id' => $report->id, 'title' => __('messages.report_updated'), 'message' => __('messages.report_waiting', ['title' => $report->title])]));
        });
        return $report->fresh()->load(['employee', 'branch', 'department', 'actions']);
    }

    private function ensureAllowedTransition(User $user, Report $report, string $action, ?string $note): void
    {
        if ($action === 'return' && blank($note)) {
            throw ValidationException::withMessages(['note' => __('messages.validation_required')]);
        }

        $role = $user->primaryRole();
        $allowed = match ($role) {
            'database_manager' => match ($report->status) {
                'draft', 'returned' => ['submit'],
                'department_review' => ['forward_branch', 'forward_general', 'return'],
                'branch_review' => ['forward_general', 'return'],
                'general_review' => ['approve', 'return'],
                default => [],
            },
            'general_manager' => $report->status === 'general_review' ? ['approve', 'return'] : [],
            'branch_manager' => $report->status === 'branch_review' ? ['forward_general', 'return'] : [],
            'department_head' => $report->status === 'department_review' ? ['forward_branch', 'forward_general', 'return'] : [],
            default => in_array($report->status, ['draft', 'returned'], true) ? ['submit'] : [],
        };

        if (! in_array($action, $allowed, true)) {
            throw ValidationException::withMessages(['action' => __('messages.report_forbidden')]);
        }
    }

    private function returnNoteCacheKey(Report $report): string
    {
        return "reports:return-note:{$report->id}";
    }
}
