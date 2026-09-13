<?php

namespace App\Modules\Communications\Services;

use App\Models\FormalCorrespondence;
use App\Models\FormalCorrespondenceEvent;
use App\Models\Task;
use App\Models\User;
use App\Modules\Communications\Repositories\Interfaces\FormalCorrespondenceRepositoryInterface;
use App\Modules\Notifications\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Modules\Tasks\Repositories\Interfaces\TaskRepositoryInterface;

/**
 * يبني تسلسل المعالجات وينبّه الجهة الهدف ويخوّل الموظفين.
 */
class FormalTimelineService
{
    public function __construct(
        private FormalPartyResolver $parties,
        private FormalCorrespondenceRepositoryInterface $formal,
        private NotificationRepositoryInterface $notifications,
        private TaskRepositoryInterface $tasks,
    ) {}

    /**
     * يسجّل معالجة جديدة: من الجهة المصدرة (الحالية) إلى الجهة المخاطبة.
     */
    public function addEvent(FormalCorrespondence $item, int $actorId, array $attributes): FormalCorrespondenceEvent
    {
        $source = $attributes['source'] ?? ['type' => null, 'id' => null, 'label' => $this->parties->originLabel($item)];
        $target = $attributes['target'];
        $date = $attributes['date'] ?? null;

        $event = $this->formal->createEvent($item, [
            'actor_id' => $actorId,
            'source_type' => $source['type'],
            'source_id' => $source['id'],
            'source_label' => $source['label'],
            'target_type' => $target['type'],
            'target_id' => $target['id'],
            'target_label' => $target['label'],
            'action_required' => $attributes['action_required'] ?? $this->parties->defaultActionRequired($target['type']),
            'decision_type' => $attributes['decision_type'] ?? null,
            'event' => $attributes['event'] ?? 'route_step',
            'to_status' => $attributes['status'] ?? $item->status,
            'note' => $attributes['note'] ?? null,
            'meta' => [
                'place' => $target['label'],
                'date' => $date,
                'source_label' => $source['label'],
                'target_label' => $target['label'],
            ],
            'created_at' => $date ?: now(),
            'updated_at' => now(),
        ]);

        $this->notifyRecipients($item, $event, $actorId);

        return $event;
    }

    /** تخويل موظف بإدارة المعالجة: إشعار + مهمة في لوحة المهام. */
    public function assign(FormalCorrespondenceEvent $event, User $assignee, User $assigner): FormalCorrespondenceEvent
    {
        $correspondence = $event->formalCorrespondence;
        $task = $this->createTaskFor($event, $correspondence, $assignee, $assigner);

        $this->formal->updateEvent($event, [
            'assigned_user_id' => $assignee->id,
            'assigned_by_id' => $assigner->id,
            'assigned_at' => now(),
            'task_id' => $task?->id,
        ]);

        $this->notifications->create([
            'user_id' => $assignee->id,
            'title' => 'تخويل بمعالجة مراسلة',
            'message' => trim(($correspondence->reference_code ?: 'مراسلة') . ' - ' . ($correspondence->subject ?: $event->target_label ?: 'معالجة جديدة')),
        ]);

        return $event->fresh();
    }

    private function createTaskFor(FormalCorrespondenceEvent $event, FormalCorrespondence $correspondence, User $assignee, User $assigner): ?Task
    {
        $departmentId = $event->target_type === 'department' && $event->target_id
            ? (int) $event->target_id
            : $assignee->department_id;

        if (! $departmentId) {
            return null;
        }

        return $this->tasks->create([
            'department_id' => $departmentId,
            'creator_id' => $assigner->id,
            'assignee_id' => $assignee->id,
            'title' => mb_substr('معالجة مراسلة: ' . ($correspondence->subject ?: $correspondence->reference_code), 0, 180),
            'description' => trim(implode("\n", array_filter([
                'رقم المراسلة: ' . $correspondence->reference_code,
                'الجهة المصدرة: ' . ($event->source_label ?: '—'),
                'الجهة المخاطبة: ' . ($event->target_label ?: '—'),
                $event->note ? 'ملاحظة: ' . $event->note : null,
            ]))),
            'status' => 'planned',
            'priority' => 'medium',
            'label' => 'مراسلات',
            'position' => $this->tasks->nextPosition($departmentId, 'planned'),
        ]);
    }

    public function notifyRecipients(FormalCorrespondence $item, FormalCorrespondenceEvent $event, int $actorId): void
    {
        $this->formal->recipientIds($event, $actorId)
            ->each(fn ($userId) => $this->notifications->create([
                'user_id' => $userId,
                'title' => 'مراسلة رسمية جديدة',
                'message' => trim(($item->reference_code ?: 'مراسلة') . ' - ' . ($item->subject ?: $event->target_label ?: 'معالجة جديدة')),
            ]));
    }

    /**
     * كتاب المعالجة يوقّعه من أصدره لا من وُجّه إليه.
     * الترتيب: كاتب المعالجة، ثم رئيس الجهة المصدرة، ثم البديل الممرَّر.
     */
    public function signerForEvent(?FormalCorrespondenceEvent $event, ?User $fallback = null): ?User
    {
        if (! $event) {
            return $fallback;
        }

        return $this->formal->eventActor($event)
            ?: ($this->formal->partyHead($event->source_type, $event->source_id) ?: $fallback);
    }
}
