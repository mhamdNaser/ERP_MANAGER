<?php

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Models\TaskStageTransition;
use App\Models\User;
use App\Modules\Tasks\Repositories\Interfaces\TaskRepositoryInterface;
use Illuminate\Support\Carbon;

/**
 * آلة حالات مسار المهمة. مرجع واحد لكل من: التحقق في الـ API، وقائمة
 * الانتقالات التي تُرسل للواجهة (allowed_transitions) حتى لا تعرض اللوحة
 * حركة يرفضها الخادم لاحقًا.
 *
 * المسار:
 *   مؤرشفة ← مخطط لها ← قيد التنفيذ ⇄ التواصل ← التدقيق ← الاعتماد
 *
 * مرحلة التواصل هي محور المسار: المكلّف يحوّل المهمة إلى موظف تواصل، وهذا
 * يضع ملاحظاته ثم إما يعيدها لقيد التنفيذ (بملاحظة إلزامية تشرح سبب الإعادة)
 * أو يرفعها إلى التدقيق تمهيدًا لاعتمادها.
 *
 * المرحلة النهائية تبقى مخزَّنة باسمها القديم `completed` وتُعرض «الاعتماد»:
 * المراحل الجديدة أُضيفت إلى المسار ولم تُلغِ القديمة، فلا داعي لإعادة تسمية
 * قيمة موجودة في آلاف الصفوف وفي سجل الحركات. الاسم المعروض في i18n.
 */
class TaskWorkflow
{
    public function __construct(private TaskRepositoryInterface $tasks) {}

    public const STATUSES = [
        'archived', 'planned', 'in_progress', 'communication', 'review', 'completed', 'cancelled',
    ];

    /** الحالات التي لم تُغلق بعد — تُحتسب عليها المواعيد والتأخير. */
    public const OPEN_STATUSES = ['archived', 'planned', 'in_progress', 'communication', 'review'];

    /** الحالات المسموح إنشاء المهمة مباشرة فيها. */
    public const INITIAL_STATUSES = ['archived', 'planned'];

    private const TRANSITIONS = [
        'archived' => ['planned', 'cancelled'],
        'planned' => ['in_progress', 'archived', 'cancelled'],
        'in_progress' => ['communication', 'planned', 'cancelled'],
        'communication' => ['in_progress', 'review', 'cancelled'],
        'review' => ['completed', 'in_progress', 'cancelled'],
        'completed' => ['in_progress'],
        'cancelled' => ['archived', 'planned'],
    ];

    /** الانتقالات التي لا يقودها المكلّف بالمهمة بل صاحب دور آخر في المسار. */
    private const GATED_SOURCES = ['communication', 'review', 'completed'];

    public function targetsFrom(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public function requiresCommunicationUser(string $target): bool
    {
        return $target === 'communication';
    }

    /**
     * إعادة المهمة من التواصل إلى التنفيذ تعني وجود ملاحظة تشرح المطلوب —
     * بدونها يفقد التخاطب بين المرحلتين معناه.
     */
    public function requiresNote(string $from, string $target): bool
    {
        return $from === 'communication' && $target === 'in_progress';
    }

    /**
     * هل يملك المستخدم قيادة المهمة من حالتها الحالية؟ مراحل التواصل
     * والتدقيق والاعتماد لها أصحابها، وما عداها يبقى بيد الموظف المكلّف.
     */
    public function canActOn(Task $task, ?User $user): bool
    {
        if (! $user) return false;
        if ($user->primaryRole() === 'database_manager') return true;

        if ($task->status === 'communication') {
            return $task->communication_user_id === $user->id || $user->can('tasks.review');
        }

        if (in_array($task->status, ['review', 'completed'], true)) {
            return $user->can('tasks.review');
        }

        if ($task->assignee_id) return $task->assignee_id === $user->id;

        return $task->creator_id === $user->id || $user->can('tasks.create');
    }

    /** الحالات التي يستطيع هذا المستخدم نقل هذه المهمة إليها الآن. */
    public function allowedTargets(Task $task, ?User $user): array
    {
        if (! $this->canActOn($task, $user)) return [];

        return array_values($this->targetsFrom($task->status));
    }

    public function assertCanMove(Task $task, User $user, string $target): void
    {
        abort_unless(
            $this->canActOn($task, $user),
            403,
            $this->deniedMessage($task->status),
        );

        abort_unless(
            $user->primaryRole() === 'database_manager' || in_array($target, $this->targetsFrom($task->status), true),
            422,
            'لا يمكن نقل المهمة من هذه المرحلة إلى المرحلة المطلوبة ضمن مسار العمل.',
        );
    }

    private function deniedMessage(string $status): string
    {
        return match (true) {
            $status === 'communication' => 'نقل المهمة من مرحلة التواصل متاح لموظف التواصل المكلّف بها أو لمن يملك صلاحية التدقيق.',
            in_array($status, ['review', 'completed'], true) => 'تحريك المهمة في مرحلة التدقيق والاعتماد يتطلب صلاحية تدقيق المهام.',
            default => 'تغيير حالة المهمة متاح فقط للموظف المكلّف بها.',
        };
    }

    /**
     * تسجيل الانتقال مع مدة بقاء المهمة في المرحلة السابقة، وتحديث أوقات
     * المهمة المشتقة منه (دخول المرحلة، بدء التنفيذ، الاعتماد).
     */
    public function recordTransition(Task $task, User $actor, ?string $from, string $to, ?string $note = null): TaskStageTransition
    {
        $enteredAt = $task->stage_entered_at ?? $task->created_at;
        $now = Carbon::now();

        return $this->tasks->recordTransition([
            'task_id' => $task->id,
            'department_id' => $task->department_id,
            'actor_id' => $actor->id,
            'assignee_id' => $task->assignee_id,
            'communication_user_id' => $task->communication_user_id,
            'from_status' => $from,
            'to_status' => $to,
            'seconds_in_previous' => $from && $enteredAt ? (int) max(0, $enteredAt->diffInSeconds($now)) : null,
            'note' => $note ?: null,
        ]);
    }

    /** أوقات المهمة التي يفرضها الانتقال إلى الحالة الجديدة. */
    public function timestampsFor(Task $task, string $target): array
    {
        $now = Carbon::now();

        return [
            'stage_entered_at' => $now,
            'started_at' => $target === 'in_progress' ? ($task->started_at ?? $now) : $task->started_at,
            'completed_at' => $target === 'completed' ? $now : null,
        ];
    }
}
