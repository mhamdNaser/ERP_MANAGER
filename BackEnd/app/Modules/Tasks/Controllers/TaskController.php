<?php

namespace App\Modules\Tasks\Controllers;

use App\Events\TaskAssigned;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Modules\Notifications\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Modules\Tasks\Repositories\Interfaces\TaskRepositoryInterface;
use App\Modules\Tasks\Resources\TaskResource;
use App\Modules\Tasks\Services\TaskActivityService;
use App\Modules\Tasks\Services\TaskWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(
        private TaskRepositoryInterface $tasks,
        private NotificationRepositoryInterface $notifications,
        private TaskActivityService $activities,
        private TaskWorkflow $workflow,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $departments = $this->tasks->departmentsFor($user);
        $showAll = $request->boolean('all') && in_array($user->primaryRole(), ['general_manager', 'database_manager']);
        $departmentId = $request->integer('department_id') ?: $user->department_id ?: $departments->first()?->id;

        abort_unless($showAll || ($departmentId && $departments->contains('id', $departmentId)), 403, 'لا يمكنك الوصول إلى لوحة هذا القسم.');

        $scoped = $showAll ? null : $departmentId;

        return response()->json([
            'departments' => $departments->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'branch' => $department->branch?->only(['id', 'name']),
            ]),
            'active_department_id' => $scoped,
            'members' => $this->tasks->members($scoped, $departments->pluck('id')->all()),
            'tasks' => TaskResource::collection($this->tasks->boardTasks($scoped)),
        ]);
    }

    public function store(Request $request): TaskResource
    {
        abort_unless($this->canCreateTasks($request->user()), 403, 'لا تملك صلاحية إنشاء المهام.');
        $data = $this->validateTask($request);
        $department = $this->tasks->findDepartment($data['department_id']);
        $this->authorizeDepartment($request->user(), $department);
        $this->authorizeAssignee($data['assignee_id'] ?? null, $department->id);

        // إنشاء المهمة وتسجيل حركتها ضمن معاملة واحدة: لو فشل تسجيل الحركة
        // لأي سبب بعد إنشاء المهمة فعليًا، يتراجع كلاهما معًا بدل أن تبقى
        // المهمة محفوظة في القاعدة بينما تظهر الواجهة خطأً — وهو ما يدفع
        // المستخدم للمحاولة مرارًا فينشئ نسخًا مكررة لا يراها حتى يحدّث اللوحة.
        $task = $this->tasks->transaction(function () use ($request, $department, $data) {
            $status = $data['status'] ?? 'archived';
            $task = $this->tasks->create([
                ...$data,
                'creator_id' => $request->user()->id,
                'status' => $status,
                'priority' => $data['priority'] ?? 'medium',
                'position' => $this->tasks->nextPosition($department->id, $status),
                'stage_entered_at' => now(),
            ]);
            $this->activities->record($task, $request->user(), 'task_created', 'أنشأ المهمة.');
            $this->workflow->recordTransition($task, $request->user(), null, $status);

            return $task;
        });
        if ($task->assignee_id) $this->notifyAssignment($task, $task->assignee_id);

        return new TaskResource($this->tasks->loadCard($task));
    }

    public function update(Request $request, Task $task): TaskResource
    {
        $this->authorizeTask($request->user(), $task);
        abort_unless($this->canUpdateTasks($request->user()), 403, 'لا تملك صلاحية تعديل المهمة.');
        $data = $this->validateTask($request, $task);
        $before = $task->only(['title', 'description', 'assignee_id', 'priority', 'label', 'due_date']);
        $departmentId = $data['department_id'] ?? $task->department_id;
        $this->authorizeAssignee($data['assignee_id'] ?? null, $departmentId);

        $this->tasks->transaction(function () use ($request, $task, $data, $before) {
            $this->tasks->update($task, $data);
            $changes = collect($task->only(array_keys($before)))
                ->filter(fn ($value, $key) => (string) ($before[$key] ?? '') !== (string) ($value ?? ''))
                ->mapWithKeys(fn ($value, $key) => [$key => ['before' => $before[$key] ?? null, 'after' => $value]])
                ->all();
            if ($changes) $this->activities->record($task, $request->user(), 'task_updated', 'عدّل بيانات المهمة.', ['changes' => $changes]);
        });
        if ($task->assignee_id && $task->assignee_id !== $before['assignee_id']) $this->notifyAssignment($task, $task->assignee_id);

        return new TaskResource($this->tasks->loadCard($task));
    }

    /**
     * نقل المهمة خطوة واحدة على مسار العمل. المسار نفسه ومن يحق له تحريكه
     * محدَّدان في TaskWorkflow، وكل انتقال يُسجَّل بتاريخه ووقته ومدة بقاء
     * المهمة في المرحلة السابقة ليغذّي لوحة الإحصائيات.
     */
    public function move(Request $request, Task $task): TaskResource
    {
        $user = $request->user();
        $this->authorizeTask($user, $task);
        $data = $request->validate([
            'status' => ['required', Rule::in(TaskWorkflow::STATUSES)],
            'position' => ['nullable', 'integer', 'min:0'],
            'communication_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $target = $data['status'];
        $this->workflow->assertCanMove($task, $user, $target);

        $note = trim((string) ($data['note'] ?? '')) ?: null;
        abort_if(
            $this->workflow->requiresNote($task->status, $target) && ! $note,
            422,
            'إعادة المهمة من التواصل إلى قيد التنفيذ تتطلب ملاحظة توضح المطلوب.',
        );

        $communicationUserId = $task->communication_user_id;
        if ($this->workflow->requiresCommunicationUser($target)) {
            $communicationUserId = $data['communication_user_id'] ?? null;
            abort_unless($communicationUserId, 422, 'يجب اختيار موظف التواصل قبل تحويل المهمة إلى مرحلة التواصل.');
            $this->authorizeAssignee($communicationUserId, $task->department_id);
            $this->authorizeCommunicationOfficer($communicationUserId, $task->department_id);
        }

        $previousStatus = $task->status;
        $routedToCommunication = $target === 'communication' && $communicationUserId !== $task->communication_user_id;
        $position = $data['position'] ?? 0;

        $this->tasks->transaction(function () use ($user, $task, $target, $previousStatus, $note, $communicationUserId, $position): void {
            // موظف التواصل الجديد يُثبَّت على النموذج قبل تسجيل الانتقال ليُحفظ
            // في سطر السجل، بينما تبقى مدة المرحلة السابقة محسوبة من وقت دخولها
            // المخزَّن على المهمة قبل التحديث.
            $task->communication_user_id = $communicationUserId;
            $transition = $this->workflow->recordTransition($task, $user, $previousStatus, $target, $note);

            $this->tasks->update($task, [
                'status' => $target,
                'position' => $position,
                'communication_user_id' => $communicationUserId,
                ...$this->workflow->timestampsFor($task, $target),
            ]);

            $this->activities->record($task, $user, 'task_moved', 'نقل المهمة إلى مرحلة جديدة.', array_filter([
                'from_status' => $previousStatus,
                'to_status' => $target,
                'note' => $note,
                'seconds_in_previous' => $transition->seconds_in_previous,
                'communication_user' => $target === 'communication' ? $this->tasks->findUser($communicationUserId)?->name : null,
            ], fn ($value) => $value !== null));
        });

        if ($routedToCommunication) $this->notifyCommunication($task, $communicationUserId);

        return new TaskResource($this->tasks->loadCard($task));
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTask($request->user(), $task);
        abort_unless($this->canDeleteTasks($request->user()), 403, 'لا تملك صلاحية حذف المهمة مع سجلها.');

        $this->tasks->deleteWithActivities($task);

        return response()->json(['message' => 'تم حذف المهمة وسجل حركاتها.']);
    }

    public function activities(Request $request): JsonResponse
    {
        $user = $request->user();
        $departments = $this->tasks->departmentIdsFor($user);
        $showAll = $request->boolean('all') && in_array($user->primaryRole(), ['general_manager', 'database_manager']);
        $departmentId = $request->integer('department_id') ?: $user->department_id ?: $departments->first();

        abort_unless($showAll || ($departmentId && $departments->contains($departmentId)), 403, 'لا يمكنك الوصول إلى سجل هذه اللوحة.');

        $departmentIds = $showAll ? $departments->all() : [$departmentId];
        $items = $this->tasks->activities($departmentIds, [
            'actor_id' => $request->integer('actor_id'),
            'action' => $request->string('action')->toString(),
            'from' => $request->date('from'),
            'to' => $request->date('to'),
        ]);

        return response()->json([
            'activities' => $items->map(fn (TaskActivity $activity) => [
                'id' => $activity->id,
                'action' => $activity->action,
                'summary' => $activity->summary,
                'task_id' => $activity->task_id,
                'task_title' => $activity->task_title,
                'details' => $activity->details,
                'actor' => $activity->actor?->only(['id', 'name', 'job_title']),
                'department' => $activity->department?->only(['id', 'name']),
                'created_at' => $activity->created_at?->toISOString(),
            ]),
            'actors' => $this->tasks->activityActors($departmentIds)->pluck('actor')->filter()->unique('id')->values(),
        ]);
    }

    private function validateTask(Request $request, ?Task $task = null): array
    {
        return $request->validate([
            'department_id' => [$task ? 'sometimes' : 'required', 'integer', 'exists:departments,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => [$task ? 'sometimes' : 'required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            // الحالة تُضبط عند الإنشاء فقط؛ بعدها تتغير حصرًا عبر move() حتى لا
            // يقفز التعديل فوق مسار العمل وشروط كل مرحلة.
            ...($task ? [] : ['status' => ['nullable', Rule::in(TaskWorkflow::INITIAL_STATUSES)]]),
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'label' => ['nullable', 'string', 'max:40'],
            'due_date' => ['nullable', 'date'],
        ]);
    }

    private function authorizeDepartment(User $user, Department $department): void
    {
        abort_unless($this->tasks->userSeesDepartment($user, $department->id), 403, 'لا يمكنك إدارة لوحة هذا القسم.');
    }

    private function authorizeTask(User $user, Task $task): void
    {
        $this->authorizeDepartment($user, $task->department);
    }

    private function authorizeAssignee(?int $assigneeId, int $departmentId): void
    {
        abort_if($assigneeId && ! $this->tasks->isDepartmentMember($assigneeId, $departmentId), 422, 'يجب أن يكون الموظف المختار من القسم نفسه.');
    }

    private function notifyAssignment(Task $task, int $assigneeId): void
    {
        $this->notifyUser($task, $assigneeId, __('messages.task_assigned'));
    }

    /**
     * الاختيار محصور بموظفي التواصل المعيَّنين في القسم. وإن لم يُعيَّن أحد بعد
     * بقي القسم على سلوكه السابق — أي عضو فيه — كي لا يتعطّل عمل قائم قبل أن
     * تُسنَد الصفة.
     */
    private function authorizeCommunicationOfficer(int $userId, int $departmentId): void
    {
        if (! $this->tasks->departmentHasCommunicationOfficer($departmentId)) {
            return;
        }

        abort_unless(
            $this->tasks->isCommunicationOfficer($userId),
            422,
            'الموظف المختار ليس من موظفي التواصل في هذا القسم.',
        );
    }

    private function notifyCommunication(Task $task, int $communicationUserId): void
    {
        $this->notifyUser($task, $communicationUserId, __('messages.task_routed_to_communication'));
    }

    /**
     * الإشعار يُحفظ أولًا ثم يُبثّ. البث فوري (ShouldBroadcastNow) فإذا كان
     * خادم الويب سوكِت متوقفًا رمى استثناءً يُفشل الطلب كله — رغم أن المهمة
     * انتقلت فعلًا والإشعار محفوظ ويظهر في مركز الإشعارات. لذلك يُسجَّل تعذّر
     * البث ولا يُبطل العملية.
     */
    private function notifyUser(Task $task, int $userId, string $title): void
    {
        $notification = $this->notifications->create([
            'user_id' => $userId,
            'task_id' => $task->id,
            'title' => $title,
            'message' => $task->title,
        ]);

        try {
            event(new TaskAssigned($task, $notification));
        } catch (\Throwable $exception) {
            Log::warning('تعذر بث إشعار المهمة مباشرة.', [
                'task_id' => $task->id,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function canCreateTasks(User $user): bool
    {
        return $user->can('tasks.create');
    }

    private function canUpdateTasks(User $user): bool
    {
        return $user->can('tasks.update');
    }

    private function canDeleteTasks(User $user): bool
    {
        return $user->can('tasks.delete_with_activities');
    }
}
