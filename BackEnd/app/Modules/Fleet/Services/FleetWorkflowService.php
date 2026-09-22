<?php

namespace App\Modules\Fleet\Services;

use App\Models\FleetMission;
use App\Models\User;
use App\Modules\Fleet\Repositories\Interfaces\FleetRepositoryInterface;
use App\Modules\Notifications\Repositories\Interfaces\NotificationRepositoryInterface;

/**
 * مسار الاعتماد: الموظف ← فرع الآليات ← المدير العام.
 * الوثيقة تُولَّد عند الاعتماد النهائي فقط.
 */
class FleetWorkflowService
{
    public function __construct(
        private FleetAccessService $access,
        private FleetDocumentService $documents,
        private FleetRepositoryInterface $fleet,
        private NotificationRepositoryInterface $notifications,
    ) {}

    public function nextStage(string $stage): ?string
    {
        $index = array_search($stage, FleetMission::STAGES, true);

        return $index === false ? null : (FleetMission::STAGES[$index + 1] ?? null);
    }

    public function statusForStage(?string $stage): string
    {
        return $stage ? 'pending_' . $stage : 'approved';
    }

    /** كل مهمة تبدأ عند فرع الآليات. */
    public function initialStage(): string
    {
        return FleetMission::STAGES[0];
    }

    public function record(FleetMission $mission, ?User $actor, string $action, ?string $note = null): void
    {
        $this->fleet->recordAction($mission, [
            'actor_id' => $actor?->id,
            'stage' => $mission->stage,
            'action' => $action,
            'note' => $note,
        ]);
    }

    public function approve(FleetMission $mission, User $actor, ?string $note = null): FleetMission
    {
        return $this->fleet->transaction(function () use ($mission, $actor, $note) {
            $this->record($mission, $actor, 'approve', $note);

            $previousStage = $mission->stage;
            $next = $this->nextStage($mission->stage);
            $mission = $this->fleet->update($mission, [
                'stage' => $next ?: 'done',
                'status' => $this->statusForStage($next),
                'decided_at' => $next ? null : now(),
            ]);

            if (! $next) {
                // الاعتماد النهائي يولّد الوثيقة موقّعةً بتوقيع من اعتمدها.
                $this->documents->generate($mission->load('user'), $actor);
                $mission = $mission->fresh();
                $message = $mission->reference_code . ' — تمت الموافقة النهائية على مهمتك.';
                $this->notify($mission->user_id, 'الموافقة على مهمة العمل', $message);
                $this->notifyWatchers($mission, 'اعتماد مهمة عمل', $mission->reference_code . ' — اعتُمدت نهائياً.', [$mission->user_id]);
            } else {
                $this->notifyStageOwners($mission);
                $this->notify(
                    $mission->user_id,
                    'تقدّمت مهمتك خطوة',
                    $mission->reference_code . ' — وافق ' . $this->stageLabel($previousStage)
                        . ' وحُوّلت إلى ' . $this->stageLabel($next) . '.',
                );
            }

            return $mission;
        });
    }

    public function reject(FleetMission $mission, User $actor, ?string $note = null): FleetMission
    {
        $rejectedAt = $mission->stage;
        $this->record($mission, $actor, 'reject', $note);
        $mission = $this->fleet->update($mission, ['status' => 'rejected', 'stage' => 'done', 'decided_at' => now()]);

        // الرفض ينهي المسار ويعود إلى الموظف فوراً.
        $message = $mission->reference_code . ' — رفضها ' . $this->stageLabel($rejectedAt) . ($note ? ': ' . $note : '.');
        $this->notify($mission->user_id, 'رفض مهمة العمل', $message);
        $this->notifyWatchers($mission, 'رفض مهمة عمل', $message, [$actor->id, $mission->user_id]);

        return $mission;
    }

    public function cancel(FleetMission $mission, User $actor): FleetMission
    {
        $this->record($mission, $actor, 'cancel', null);

        return $this->fleet->update($mission, ['status' => 'cancelled', 'stage' => 'done', 'decided_at' => now()]);
    }

    /** أطراف المسار: فرع الآليات والمدير العام. */
    private function notifyWatchers(FleetMission $mission, string $title, string $message, array $exceptIds = []): void
    {
        $except = array_values(array_filter($exceptIds));

        $this->access->watcherIds()
            ->reject(fn ($id) => in_array($id, $except))
            ->each(fn ($id) => $this->notify($id, $title, $message));
    }

    public function stageLabel(?string $stage): string
    {
        return ['fleet' => 'فرع الآليات', 'gm' => 'الإدارة العامة'][$stage] ?? 'الجهة المختصة';
    }

    public function notifyStageOwners(FleetMission $mission): void
    {
        $this->access->stageApproverIds($mission)
            ->reject(fn ($id) => (int) $id === (int) $mission->created_by_id)
            ->each(fn ($id) => $this->notify(
                $id,
                'مهمة عمل بانتظار قرارك',
                $mission->reference_code . ' — ' . ($mission->user?->name ?: ''),
            ));
    }

    private function notify(int $userId, string $title, string $message): void
    {
        $this->notifications->create(['user_id' => $userId, 'title' => $title, 'message' => trim($message)]);
    }
}
