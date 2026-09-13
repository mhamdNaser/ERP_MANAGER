<?php

namespace App\Modules\Communications\Services;

use App\Models\FormalCorrespondence;
use App\Models\FormalCorrespondenceDocument;
use App\Models\FormalCorrespondenceEvent;
use App\Models\User;
use App\Modules\Communications\Repositories\Interfaces\FormalCorrespondenceRepositoryInterface;

/**
 * يضبط التسلسل: لا ترى الجهة إلا المعالجات التي وصلت إليها أو صدرت عنها.
 */
class FormalVisibilityService
{
    public function __construct(private FormalCorrespondenceRepositoryInterface $formal) {}

    /** الديوان وحده يرى كامل السلسلة بحكم مسؤوليته عن التسجيل والتوجيه. */
    public function canViewFullThread(?User $user): bool
    {
        return $this->isDiwanUser($user);
    }

    public function canCreate(?User $user): bool
    {
        return $user && ($user->role === 'database_manager' || $this->isDiwanUser($user));
    }

    public function isDiwanUser(?User $user): bool
    {
        if (! $user) return false;

        $office = $this->formal->officeOf($user);

        return $office?->code === 'REGISTRY'
            || (is_string($office?->name) && str($office->name)->contains(['ديوان', 'السجل', 'registry']));
    }

    public function eventVisibleTo(FormalCorrespondenceEvent $event, User $user): bool
    {
        if ((int) $event->actor_id === (int) $user->id) return true;
        if ($event->assigned_user_id && (int) $event->assigned_user_id === (int) $user->id) return true;

        return $this->partyMatchesUser($event->target_type, $event->target_id, $user)
            || $this->partyMatchesUser($event->source_type, $event->source_id, $user);
    }

    /** هل ينتمي المستخدم إلى الجهة المحددة (نوعاً ومعرّفاً)؟ */
    public function partyMatchesUser(?string $type, $id, User $user): bool
    {
        $id = $id !== null && $id !== '' ? (int) $id : null;

        return match ($type) {
            'diwan' => (bool) ($this->userOfficeName($user) && str($this->userOfficeName($user))->contains(['ديوان', 'السجل', 'registry'])),
            'general_manager' => $user->role === 'general_manager',
            'branch' => $id !== null && $id === (int) $user->branch_id,
            'department' => $id !== null && $id === (int) $user->department_id,
            'office' => $id !== null && $id === (int) $user->office_id,
            'user' => $id !== null && $id === (int) $user->id,
            default => false,
        };
    }

    public function userOfficeName(User $user): ?string
    {
        return $this->formal->officeOf($user)?->name;
    }

    public function loaded(FormalCorrespondence $correspondence): FormalCorrespondence
    {
        return $this->formal->loadForDisplay($correspondence);
    }

    /** يعيد المراسلة بعد تصفية معالجاتها ووثائقها حسب صلاحية المستخدم. */
    public function visibleFor(FormalCorrespondence $correspondence, ?User $user): FormalCorrespondence
    {
        if ($this->canViewFullThread($user)) {
            $correspondence->visible_events_count = $correspondence->events->count();
            $this->attachEditPermissions($correspondence, $correspondence->events, $user);

            return $correspondence;
        }

        if (! $user) {
            $correspondence->setRelation('events', $correspondence->events->take(0)->values());
            $correspondence->setRelation('documents', $correspondence->documents->take(0)->values());
            $correspondence->visible_events_count = 0;

            return $correspondence;
        }

        $orderedEvents = $correspondence->events
            ->sortBy(fn (FormalCorrespondenceEvent $event) => sprintf('%020d-%020d', $event->created_at?->getTimestamp() ?? 0, $event->id))
            ->values();
        $accessibleIndexes = $orderedEvents
            ->keys()
            ->filter(fn (int $index) => $this->eventVisibleTo($orderedEvents[$index], $user));
        $lastAccessibleIndex = $accessibleIndexes->isEmpty() ? null : $accessibleIndexes->max();

        // عند وصول المراسلة إلى الجهة أو الموظف، تظهر له المعالجات السابقة حتى نقطة وصولها.
        $visibleEvents = $lastAccessibleIndex === null
            ? $orderedEvents->take(0)
            : $orderedEvents->take($lastAccessibleIndex + 1)->values();
        $visibleEventIds = $visibleEvents->pluck('id')->all();

        $correspondence->setRelation('events', $visibleEvents);
        $correspondence->setRelation(
            'documents',
            $correspondence->documents
                ->filter(fn (FormalCorrespondenceDocument $document) => in_array($document->formal_correspondence_event_id, $visibleEventIds, true))
                ->values()
        );
        $this->attachEditPermissions($correspondence, $visibleEvents, $user);
        $correspondence->visible_events_count = $visibleEvents->count();

        return $correspondence;
    }

    private function attachEditPermissions(FormalCorrespondence $correspondence, $events, User $user): void
    {
        $correspondence->setAttribute('can_edit', $this->canEditCorrespondence($user, $correspondence));

        $events->each(function (FormalCorrespondenceEvent $event) use ($user) {
            $canEdit = $this->canEditEvent($user, $event);
            $event->setAttribute('can_edit', $canEdit);
            $event->documents->each(fn (FormalCorrespondenceDocument $document) => $this->decorateDocument($document, $canEdit));
        });

        $permissionsByEvent = $events->mapWithKeys(
            fn (FormalCorrespondenceEvent $event) => [$event->id => (bool) $event->getAttribute('can_edit')]
        );
        $correspondence->documents->each(function (FormalCorrespondenceDocument $document) use ($permissionsByEvent) {
            $this->decorateDocument(
                $document,
                (bool) $permissionsByEvent->get($document->formal_correspondence_event_id, false)
            );
        });
    }

    private function decorateDocument(FormalCorrespondenceDocument $document, bool $canEdit): void
    {
        $document->setAttribute('can_edit', $canEdit);
    }

    public function visibleLoaded(FormalCorrespondence $correspondence, ?User $user): FormalCorrespondence
    {
        return $this->visibleFor($this->loaded($correspondence), $user);
    }

    public function canDelete(?User $user): bool
    {
        return $this->canViewFullThread($user);
    }

    public function canAccessEvent(?User $user, FormalCorrespondenceEvent $event): bool
    {
        if (! $user) return false;

        return $this->canViewFullThread($user) || $this->eventVisibleTo($event, $user);
    }

    public function canProcess(?User $user, FormalCorrespondence $correspondence): bool
    {
        if (! $user) return false;

        $latest = $this->formal->latestEvent($correspondence);
        if (! $latest) return false;

        if ((int) $latest->assigned_user_id === (int) $user->id) return true;

        return $this->partyMatchesUser($latest->target_type, $latest->target_id, $user);
    }

    public function canEditCorrespondence(?User $user, FormalCorrespondence $correspondence): bool
    {
        if (! $user) return false;

        $firstEvent = $this->formal->firstEvent($correspondence);
        if (! $firstEvent || $this->formal->hasEventAfter($correspondence, $firstEvent->id)) {
            return false;
        }

        if ($this->isInternalSource($firstEvent->source_type)) {
            return $this->partyMatchesUser($firstEvent->source_type, $firstEvent->source_id, $user);
        }

        return (int) $correspondence->creator_id === (int) $user->id
            || (int) $firstEvent->actor_id === (int) $user->id;
    }

    /** تعديل المعالجة حق منشئها وحده، وما لم تُتبع بمعالجة أحدث. */
    public function canEditEvent(?User $user, FormalCorrespondenceEvent $event): bool
    {
        if (! $user) return false;

        if ($this->formal->hasEventAfter($event->formalCorrespondence, $event->id)) return false;

        return (int) $event->actor_id === (int) $user->id;
    }

    private function isInternalSource(?string $type): bool
    {
        return in_array($type, ['diwan', 'general_manager', 'branch', 'department', 'office', 'user'], true);
    }

    /** صاحب المعالجة الحالي هو من يستطيع تخويل موظف بها. */
    public function ownsStage(?User $user, FormalCorrespondenceEvent $event): bool
    {
        return $user && $this->partyMatchesUser($event->target_type, $event->target_id, $user);
    }

    public function canAssignStage(?User $user, FormalCorrespondenceEvent $event): bool
    {
        return $this->ownsStage($user, $event)
            && in_array($user->role, ['department_head', 'branch_manager', 'office_manager', 'general_manager', 'database_manager'], true);
    }
}
