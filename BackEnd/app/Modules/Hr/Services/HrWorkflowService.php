<?php

namespace App\Modules\Hr\Services;

use App\Models\HrLeaveBalance;
use App\Models\HrRequest;
use App\Models\User;
use App\Modules\Hr\Repositories\Interfaces\HrRepositoryInterface;
use App\Modules\Notifications\Repositories\Interfaces\NotificationRepositoryInterface;

/**
 * مسار الاعتماد: الموظف ← الموارد البشرية ← المدير العام.
 * الرصيد يُخصم عند الاعتماد النهائي فقط.
 */
class HrWorkflowService
{
    public function __construct(
        private HrAccessService $access,
        private HrDocumentService $documents,
        private HrRepositoryInterface $hr,
        private NotificationRepositoryInterface $notifications,
    ) {}

    public function nextStage(string $stage): ?string
    {
        $index = array_search($stage, HrRequest::STAGES, true);

        return $index === false ? null : (HrRequest::STAGES[$index + 1] ?? null);
    }

    public function statusForStage(?string $stage): string
    {
        return $stage ? 'pending_' . $stage : 'approved';
    }

    /** كل طلب يبدأ عند الموارد البشرية. */
    public function initialStage(): string
    {
        return HrRequest::STAGES[0];
    }

    public function record(HrRequest $request, ?User $actor, string $action, ?string $note = null): void
    {
        $this->hr->recordAction($request, [
            'actor_id' => $actor?->id,
            'stage' => $request->stage,
            'action' => $action,
            'note' => $note,
        ]);
    }

    public function approve(HrRequest $request, User $actor, ?string $note = null): HrRequest
    {
        return $this->hr->transaction(function () use ($request, $actor, $note) {
            $this->record($request, $actor, 'approve', $note);

            $previousStage = $request->stage;
            $next = $this->nextStage($request->stage);
            $request = $this->hr->update($request, [
                'stage' => $next ?: 'done',
                'status' => $this->statusForStage($next),
                'decided_at' => $next ? null : now(),
            ]);

            if (! $next) {
                $this->consumeBalance($request);
                // الاعتماد النهائي يولّد الوثيقة موقّعةً بتوقيع من اعتمدها.
                $this->documents->generate($request->load('user'), $actor);
                $request = $request->fresh();
                $message = $request->reference_code . ' — تمت الموافقة النهائية على طلبك.';
                $this->notify($request->user_id, 'الموافقة على طلبك', $message);
                $this->notifyWatchers($request, 'اعتماد طلب موظف', $request->reference_code . ' — اعتُمد نهائياً.', [$request->user_id]);
            } else {
                $this->notifyStageOwners($request);
                $this->notify(
                    $request->user_id,
                    'تقدّم طلبك خطوة',
                    $request->reference_code . ' — وافقت ' . $this->stageLabel($previousStage)
                        . ' وحُوّل إلى ' . $this->stageLabel($next) . '.',
                );
            }

            return $request;
        });
    }

    public function reject(HrRequest $request, User $actor, ?string $note = null): HrRequest
    {
        $rejectedAt = $request->stage;
        $this->record($request, $actor, 'reject', $note);
        $request = $this->hr->update($request, ['status' => 'rejected', 'stage' => 'done', 'decided_at' => now()]);

        // الرفض ينهي المسار ويعود إلى الموظف فوراً.
        $message = $request->reference_code . ' — رفضته ' . $this->stageLabel($rejectedAt) . ($note ? ': ' . $note : '.');
        $this->notify($request->user_id, 'رفض طلبك', $message);
        $this->notifyWatchers($request, 'رفض طلب موظف', $message, [$actor->id, $request->user_id]);

        return $request;
    }

    public function cancel(HrRequest $request, User $actor): HrRequest
    {
        $this->record($request, $actor, 'cancel', null);

        return $this->hr->update($request, ['status' => 'cancelled', 'stage' => 'done', 'decided_at' => now()]);
    }

    /** الإجازة السنوية وحدها تُخصم من الرصيد. */
    public function consumeBalance(HrRequest $request): void
    {
        if ($request->type !== 'leave' || $request->subtype !== 'annual' || ! $request->days) {
            return;
        }

        $balance = $this->balanceFor($request->user_id, (int) ($request->start_date?->format('Y') ?: now()->year));
        $this->hr->addUsedDays($balance, (float) $request->days);
    }

    public function balanceFor(int $userId, ?int $year = null): HrLeaveBalance
    {
        return $this->hr->balanceFor($userId, $year ?: (int) now()->year);
    }

    /** أطراف المسار: الموارد البشرية والمدير العام. */
    private function notifyWatchers(HrRequest $request, string $title, string $message, array $exceptIds = []): void
    {
        $except = array_values(array_filter($exceptIds));

        $this->access->watcherIds()
            ->reject(fn ($id) => in_array($id, $except))
            ->each(fn ($id) => $this->notify($id, $title, $message));
    }

    public function stageLabel(?string $stage): string
    {
        return ['hr' => 'الموارد البشرية', 'gm' => 'الإدارة العامة'][$stage] ?? 'الجهة المختصة';
    }

    public function notifyStageOwners(HrRequest $request): void
    {
        $this->access->stageApproverIds($request)
            ->reject(fn ($id) => (int) $id === (int) $request->created_by_id)
            ->each(fn ($id) => $this->notify(
                $id,
                'طلب موارد بشرية بانتظار قرارك',
                $request->reference_code . ' — ' . ($request->user?->name ?: ''),
            ));
    }

    private function notify(int $userId, string $title, string $message): void
    {
        $this->notifications->create(['user_id' => $userId, 'title' => $title, 'message' => trim($message)]);
    }
}
