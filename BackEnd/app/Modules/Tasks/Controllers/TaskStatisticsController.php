<?php

namespace App\Modules\Tasks\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Modules\Tasks\Repositories\Interfaces\TaskRepositoryInterface;
use App\Modules\Tasks\Services\TaskWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * لوحة إحصائيات المهام: كم مهمة أنجز كل موظف، وكم استغرق في كل مرحلة، وأين
 * يقع التأخير. المصدر هو جدول انتقالات المراحل (task_stage_transitions) الذي
 * يسجّل كل حركة بوقتها ومدة المرحلة السابقة لها، لا سجل الحركات المخصص للعرض.
 *
 * الوصول مقيَّد بصلاحية tasks.statistics.view — ممنوحة افتراضيًا لرئيس القسم
 * ومدير الفرع، وقابلة للمنح لموظف مشرف من صفحة الأدوار والصلاحيات.
 */
class TaskStatisticsController extends Controller
{
    public function __construct(private TaskRepositoryInterface $tasks) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('tasks.statistics.view'), 403, 'لا تملك صلاحية عرض إحصائيات المهام.');

        $departments = $this->tasks->departmentsFor($user);
        $showAll = $request->boolean('all') && in_array($user->primaryRole(), ['general_manager', 'database_manager']);
        $departmentId = $request->integer('department_id') ?: $user->department_id ?: $departments->first()?->id;

        abort_unless($showAll || ($departmentId && $departments->contains('id', $departmentId)), 403, 'لا يمكنك الوصول إلى إحصائيات هذا القسم.');

        $departmentIds = $showAll ? $departments->pluck('id')->all() : [$departmentId];
        $from = $request->date('from')?->startOfDay();
        $to = $request->date('to')?->endOfDay();

        $tasks = $this->tasks->tasksInRange($departmentIds, $from, $to);
        $transitions = $this->tasks->transitionsInRange($departmentIds, $from, $to);

        return response()->json([
            'departments' => $departments->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'branch' => $department->branch?->only(['id', 'name']),
            ]),
            'active_department_id' => $showAll ? null : $departmentId,
            'range' => ['from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'summary' => $this->summary($tasks, $transitions),
            'stages' => $this->stages($transitions),
            'employees' => $this->employees($tasks, $transitions, $departmentIds),
            'communicators' => $this->communicators($tasks, $transitions, $departmentIds),
            'trend' => $this->trend($tasks),
        ]);
    }

    /**
     * المرحلة النهائية مخزَّنة باسمها القديم `completed` ومعروضة «الاعتماد»،
     * فمفاتيح الرد تحمل اسم المرحلة كما يراها المستخدم — وهو الاصطلاح نفسه
     * المتّبع في DashboardAnalyticsService مع حالات التقارير.
     */
    private function summary(Collection $tasks, Collection $transitions): array
    {
        $approved = $tasks->where('status', 'completed');
        $withDueDate = $approved->filter->due_date;

        return [
            'total' => $tasks->count(),
            'open' => $tasks->whereIn('status', TaskWorkflow::OPEN_STATUSES)->count(),
            'approved' => $approved->count(),
            'cancelled' => $tasks->where('status', 'cancelled')->count(),
            'overdue' => $tasks->filter(fn (Task $task) => $this->isOverdue($task))->count(),
            'in_communication' => $tasks->where('status', 'communication')->count(),
            'in_review' => $tasks->where('status', 'review')->count(),
            'avg_cycle_hours' => $this->averageHours($approved->map(fn (Task $task) => $this->cycleSeconds($task))),
            'avg_execution_hours' => $this->averageHours($approved->map(fn (Task $task) => $this->executionSeconds($task))),
            'on_time_rate' => $withDueDate->isEmpty() ? null : (int) round(
                $withDueDate->filter(fn (Task $task) => $this->approvedOnTime($task))->count() / $withDueDate->count() * 100,
            ),
            'returns' => $transitions->where('from_status', 'communication')->where('to_status', 'in_progress')->count(),
        ];
    }

    /** متوسط زمن البقاء في كل مرحلة، وعدد المرات التي غادرتها مهمة. */
    private function stages(Collection $transitions): array
    {
        $left = $transitions->whereNotNull('from_status')->groupBy('from_status');

        return collect(TaskWorkflow::STATUSES)
            ->map(fn (string $status) => [
                'status' => $status,
                'count' => $left->get($status, collect())->count(),
                'avg_hours' => $this->averageHours($left->get($status, collect())->pluck('seconds_in_previous')),
            ])
            ->filter(fn (array $stage) => $stage['count'] > 0)
            ->values()
            ->all();
    }

    /**
     * صف لكل موظف في نطاق اللوحة — حتى من لا مهام له، لأن غياب الإنجاز
     * معلومة يحتاجها رئيس القسم تمامًا كما يحتاج أرقام المنجزين.
     */
    private function employees(Collection $tasks, Collection $transitions, array $departmentIds): array
    {
        $byAssignee = $tasks->whereNotNull('assignee_id')->groupBy('assignee_id');
        $executionByAssignee = $transitions->where('from_status', 'in_progress')->whereNotNull('assignee_id')->groupBy('assignee_id');
        $returnsByAssignee = $transitions->where('from_status', 'communication')->where('to_status', 'in_progress')
            ->whereNotNull('assignee_id')->groupBy('assignee_id');

        return $this->people($tasks, $departmentIds, 'assignee')
            ->map(function (array $person) use ($byAssignee, $executionByAssignee, $returnsByAssignee) {
                $owned = $byAssignee->get($person['id'], collect());
                $approved = $owned->where('status', 'completed');
                $withDueDate = $approved->filter->due_date;

                return [
                    ...$person,
                    'total' => $owned->count(),
                    'open' => $owned->whereIn('status', TaskWorkflow::OPEN_STATUSES)->count(),
                    'approved' => $approved->count(),
                    'overdue' => $owned->filter(fn (Task $task) => $this->isOverdue($task))->count(),
                    'in_communication' => $owned->where('status', 'communication')->count(),
                    'avg_cycle_hours' => $this->averageHours($approved->map(fn (Task $task) => $this->cycleSeconds($task))),
                    'avg_execution_hours' => $this->averageHours($executionByAssignee->get($person['id'], collect())->pluck('seconds_in_previous')),
                    'returns' => $returnsByAssignee->get($person['id'], collect())->count(),
                    'on_time_rate' => $withDueDate->isEmpty() ? null : (int) round(
                        $withDueDate->filter(fn (Task $task) => $this->approvedOnTime($task))->count() / $withDueDate->count() * 100,
                    ),
                    'completion_rate' => $owned->isEmpty() ? null : (int) round($approved->count() / $owned->count() * 100),
                ];
            })
            ->sortByDesc(fn (array $row) => [$row['approved'], $row['total']])
            ->values()
            ->all();
    }

    /** أداء موظفي التواصل: كم مهمة استلموا، وكم بقيت عندهم، وكيف أخرجوها. */
    private function communicators(Collection $tasks, Collection $transitions, array $departmentIds): array
    {
        $received = $transitions->where('to_status', 'communication')->whereNotNull('communication_user_id')->groupBy('communication_user_id');
        $handled = $transitions->where('from_status', 'communication')->whereNotNull('communication_user_id')->groupBy('communication_user_id');
        $pending = $tasks->where('status', 'communication')->whereNotNull('communication_user_id')->groupBy('communication_user_id');

        return $this->people($tasks, $departmentIds, 'communicationUser')
            ->map(function (array $person) use ($received, $handled, $pending) {
                $done = $handled->get($person['id'], collect());

                return [
                    ...$person,
                    'received' => $received->get($person['id'], collect())->count(),
                    'pending' => $pending->get($person['id'], collect())->count(),
                    'returned' => $done->where('to_status', 'in_progress')->count(),
                    'forwarded' => $done->where('to_status', 'review')->count(),
                    'avg_response_hours' => $this->averageHours($done->pluck('seconds_in_previous')),
                ];
            })
            ->filter(fn (array $row) => $row['received'] > 0 || $row['pending'] > 0)
            ->sortByDesc('received')
            ->values()
            ->all();
    }

    /** موظفو النطاق: الأعضاء النشطون، مضافًا إليهم من يحمل مهامًا وغادر القسم. */
    private function people(Collection $tasks, array $departmentIds, string $relation): Collection
    {
        $members = $this->tasks->activeMembers($departmentIds)
            ->mapWithKeys(fn (User $member) => [$member->id => [
                'id' => $member->id,
                'name' => $member->name,
                'job_title' => $member->job_title,
            ]]);

        $historical = $tasks->pluck($relation)->filter()
            ->mapWithKeys(fn (User $person) => [$person->id => [
                'id' => $person->id,
                'name' => $person->name,
                'job_title' => $person->job_title,
            ]]);

        return $members->union($historical)->values();
    }

    private function trend(Collection $tasks): array
    {
        $created = $tasks->groupBy(fn (Task $task) => $task->created_at->copy()->startOfWeek()->format('Y-m-d'));
        // الاعتماد يُقاس بالحالة لا بوجود الختم الزمني وحده، فلا تُحسب مهمة
        // أُعيدت إلى التنفيذ بعد اعتمادها ضمن معتمدات ذلك الأسبوع.
        $approved = $tasks->where('status', 'completed')->filter->completed_at
            ->groupBy(fn (Task $task) => $task->completed_at->copy()->startOfWeek()->format('Y-m-d'));

        return collect(range(7, 0))->map(function (int $weeksAgo) use ($created, $approved) {
            $week = Carbon::now()->startOfWeek()->subWeeks($weeksAgo);
            $key = $week->format('Y-m-d');

            return [
                'key' => $key,
                'label' => $week->format('d/m'),
                'created' => $created->get($key, collect())->count(),
                'approved' => $approved->get($key, collect())->count(),
            ];
        })->values()->all();
    }

    private function cycleSeconds(Task $task): ?int
    {
        return $task->completed_at ? (int) $task->created_at->diffInSeconds($task->completed_at) : null;
    }

    /** الزمن الفعلي للتنفيذ: من أول انتقال إلى قيد التنفيذ حتى الاعتماد. */
    private function executionSeconds(Task $task): ?int
    {
        return $task->completed_at && $task->started_at ? (int) $task->started_at->diffInSeconds($task->completed_at) : null;
    }

    private function approvedOnTime(Task $task): bool
    {
        return (bool) $task->completed_at && $task->completed_at->toDateString() <= $task->due_date->toDateString();
    }

    private function isOverdue(Task $task): bool
    {
        return $task->due_date
            && in_array($task->status, TaskWorkflow::OPEN_STATUSES, true)
            && $task->due_date->toDateString() < Carbon::today()->toDateString();
    }

    private function averageHours(Collection $seconds): ?float
    {
        $values = $seconds->filter(fn ($value) => $value !== null);

        return $values->isEmpty() ? null : round($values->avg() / 3600, 1);
    }

}
