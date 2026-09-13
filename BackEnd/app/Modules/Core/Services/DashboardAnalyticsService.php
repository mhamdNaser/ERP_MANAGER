<?php

namespace App\Modules\Core\Services;

use App\Models\User;
use App\Modules\Core\Repositories\Interfaces\CoreRepositoryInterface;
use App\Modules\Forms\Services\CustomFormService;
use App\Modules\Notifications\Repositories\Interfaces\NotificationRepositoryInterface;

/**
 * يرتّب أرقام اللوحة الرئيسية في منحنيات وتوزيعات. الاستعلامات نفسها في
 * CoreRepository، وهذه الطبقة لا تعرف عن القاعدة شيئاً.
 */
class DashboardAnalyticsService
{
    public function __construct(
        private CoreRepositoryInterface $core,
        private NotificationRepositoryInterface $notifications,
        private CustomFormService $forms,
    ) {}

    public function for(User $user): array
    {
        [, $assignedForms] = $this->forms->formsFor($user);

        return [
            'report_trend' => $this->monthlyReportTrend($user),
            'task_trend' => $this->weeklyTaskTrend($user),
            'status_distribution' => $this->statusDistribution($user),
            'tasks' => $this->core->taskCounts($user),
            'attention' => [
                'unread_notifications' => $this->notifications->unreadCountFor($user),
                'assigned_forms' => $assignedForms->count(),
                'overdue_tasks' => $this->core->overdueTasksCount($user),
                'returned_reports' => $this->core->returnedReportsCount($user),
            ],
        ];
    }

    private function monthlyReportTrend(User $user): array
    {
        $start = now()->startOfMonth()->subMonths(5);
        $counts = $this->core->reportsSince($user, $start)->groupBy(fn ($report) => $report->created_at->format('Y-m'));

        return collect(range(5, 0))->map(function (int $monthsAgo) use ($counts) {
            $month = now()->startOfMonth()->subMonths($monthsAgo);
            $items = $counts->get($month->format('Y-m'), collect());
            return [
                'key' => $month->format('Y-m'),
                'label' => $month->translatedFormat('M'),
                'total' => $items->count(),
                'completed' => $items->where('status', 'approved')->count(),
            ];
        })->values()->all();
    }

    private function statusDistribution(User $user): array
    {
        $counts = $this->core->reportStatusCounts($user);

        return collect(['draft', 'department_review', 'branch_review', 'general_review', 'returned', 'approved'])
            ->map(fn (string $status) => ['status' => $status, 'count' => (int) ($counts[$status] ?? 0)])
            ->all();
    }

    private function weeklyTaskTrend(User $user): array
    {
        $start = now()->startOfWeek()->subWeeks(7);
        $items = $this->core->tasksTouchedSince($user, $start);
        $created = $items->groupBy(fn ($task) => $task->created_at->copy()->startOfWeek()->format('Y-m-d'));
        $completed = $items->filter->completed_at->groupBy(fn ($task) => $task->completed_at->copy()->startOfWeek()->format('Y-m-d'));

        return collect(range(7, 0))->map(function (int $weeksAgo) use ($created, $completed) {
            $week = now()->startOfWeek()->subWeeks($weeksAgo);
            $key = $week->format('Y-m-d');
            return [
                'key' => $key,
                'label' => $week->format('d/m'),
                'created' => $created->get($key, collect())->count(),
                'completed' => $completed->get($key, collect())->count(),
            ];
        })->values()->all();
    }
}
