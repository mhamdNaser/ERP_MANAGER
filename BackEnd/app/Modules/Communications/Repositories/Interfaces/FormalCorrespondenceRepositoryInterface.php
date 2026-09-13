<?php
namespace App\Modules\Communications\Repositories\Interfaces;

use App\Models\ExternalEntity;
use App\Models\FormalCorrespondence;
use App\Models\FormalCorrespondenceDocument;
use App\Models\FormalCorrespondenceEvent;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

interface FormalCorrespondenceRepositoryInterface
{
    public function all(): Collection;
    public function loadForDisplay(FormalCorrespondence $item): FormalCorrespondence;
    public function create(array $attributes): FormalCorrespondence;
    public function update(FormalCorrespondence $item, array $attributes): FormalCorrespondence;
    public function delete(FormalCorrespondence $item): void;
    /** تسلسل الرقم المرجعي لمراسلات اليوم. */
    public function nextDailySequence(): int;

    /** أدلة الأطراف التي تغذّي نموذج الإنشاء. */
    public function externalEntities(): Collection;
    public function branchesWithDepartments(): Collection;
    public function activeOffices(): Collection;
    public function generalManagers(): Collection;
    public function activeStaff(): Collection;
    public function recentCorrespondences(int $limit): Collection;
    public function partyName(string $type, int $id): ?string;
    public function externalEntityByName(string $name): ExternalEntity;
    public function knownPlaceLabels(): array;

    public function createEvent(FormalCorrespondence $item, array $attributes): FormalCorrespondenceEvent;
    public function updateEvent(FormalCorrespondenceEvent $event, array $attributes): FormalCorrespondenceEvent;
    public function deleteEvent(FormalCorrespondenceEvent $event): void;
    public function latestEvent(FormalCorrespondence $item): ?FormalCorrespondenceEvent;
    public function firstEvent(FormalCorrespondence $item): ?FormalCorrespondenceEvent;
    /** هل أُتبعت هذه المعالجة بأحدث منها؟ — شرط منع التعديل. */
    public function hasEventAfter(FormalCorrespondence $item, int $eventId): bool;
    public function nextBookSequence(): int;
    public function createEventResponse(FormalCorrespondenceEvent $event, array $attributes): void;
    public function createDocuments(FormalCorrespondence $item, array $rows): void;

    public function findDocument(int $documentId): FormalCorrespondenceDocument;
    public function updateDocument(FormalCorrespondenceDocument $document, array $attributes): void;

    /** المعنيّون بالمعالجة: من يُشعَر بها، ومن يصلح للتخويل، ورئيس الجهة. */
    public function recipientIds(FormalCorrespondenceEvent $event, int $exceptActorId): BaseCollection;
    public function assignableUser(FormalCorrespondenceEvent $event, int $userId): ?User;
    public function partyHead(?string $type, $id): ?User;
    public function eventActor(FormalCorrespondenceEvent $event): ?User;
    public function officeOf(User $user): ?Office;

    public function transaction(callable $callback): mixed;
}
