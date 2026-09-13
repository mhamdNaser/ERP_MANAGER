<?php
namespace App\Modules\Communications\Repositories\Eloquent;

use App\Models\Branch;
use App\Models\Department;
use App\Models\ExternalEntity;
use App\Models\FormalCorrespondence;
use App\Models\FormalCorrespondenceDocument;
use App\Models\FormalCorrespondenceEvent;
use App\Models\Office;
use App\Models\User;
use App\Modules\Communications\Repositories\Interfaces\FormalCorrespondenceRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

class FormalCorrespondenceRepository implements FormalCorrespondenceRepositoryInterface
{
    /** كل ما تحتاجه شاشة المراسلة: أطرافها ومعالجاتها ووثائقها وردودها. */
    private const RELATIONS = [
        'creator:id,name,role',
        'parent:id,reference_code,subject',
        'senderExternalEntity:id,name',
        'recipientExternalEntity:id,name',
        'recipientBranch:id,name',
        'recipientDepartment:id,name',
        'events.actor:id,name',
        'events.assignedUser:id,name,job_title',
        'events.assignedBy:id,name',
        'events.documents',
        'events.responses.actor:id,name',
        'documents',
    ];

    public function all(): Collection
    {
        return FormalCorrespondence::with(self::RELATIONS)->latest()->get();
    }

    public function loadForDisplay(FormalCorrespondence $item): FormalCorrespondence
    {
        return $item->load(self::RELATIONS);
    }

    public function create(array $attributes): FormalCorrespondence
    {
        return FormalCorrespondence::create($attributes);
    }

    public function update(FormalCorrespondence $item, array $attributes): FormalCorrespondence
    {
        $item->update($attributes);

        return $item;
    }

    public function delete(FormalCorrespondence $item): void
    {
        $item->delete();
    }

    public function nextDailySequence(): int
    {
        return FormalCorrespondence::whereDate('created_at', today())->count() + 1;
    }

    public function externalEntities(): Collection
    {
        return ExternalEntity::orderBy('name')->get(['id', 'name', 'code']);
    }

    public function branchesWithDepartments(): Collection
    {
        return Branch::with(['departments:id,name,branch_id'])->orderBy('name')->get(['id', 'name']);
    }

    public function activeOffices(): Collection
    {
        return Office::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    public function generalManagers(): Collection
    {
        return User::where('role', 'general_manager')->orderBy('name')->get(['id', 'name']);
    }

    public function activeStaff(): Collection
    {
        return User::where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'job_title', 'role', 'branch_id', 'department_id', 'office_id']);
    }

    public function recentCorrespondences(int $limit): Collection
    {
        return FormalCorrespondence::latest()->limit($limit)
            ->get(['id', 'reference_code', 'subject', 'direction', 'created_at']);
    }

    public function partyName(string $type, int $id): ?string
    {
        return match ($type) {
            'general_manager', 'user' => User::find($id)?->name,
            'branch' => Branch::find($id)?->name,
            'department' => Department::find($id)?->name,
            'office' => Office::find($id)?->name,
            'external_entity' => ExternalEntity::find($id)?->name,
            default => null,
        };
    }

    public function externalEntityByName(string $name): ExternalEntity
    {
        return ExternalEntity::firstOrCreate(['name' => $name]);
    }

    public function knownPlaceLabels(): array
    {
        $firstPlaces = FormalCorrespondence::whereNotNull('first_place')->pluck('first_place')->filter();
        $eventPlaces = FormalCorrespondenceEvent::whereNotNull('target_label')->pluck('target_label')->filter();

        return $firstPlaces->merge($eventPlaces)->unique()->sort()->values()->all();
    }

    public function createEvent(FormalCorrespondence $item, array $attributes): FormalCorrespondenceEvent
    {
        return $item->events()->create($attributes);
    }

    public function updateEvent(FormalCorrespondenceEvent $event, array $attributes): FormalCorrespondenceEvent
    {
        $event->update($attributes);

        return $event;
    }

    public function deleteEvent(FormalCorrespondenceEvent $event): void
    {
        $event->delete();
    }

    public function latestEvent(FormalCorrespondence $item): ?FormalCorrespondenceEvent
    {
        return $item->events()->latest('id')->first();
    }

    public function firstEvent(FormalCorrespondence $item): ?FormalCorrespondenceEvent
    {
        return $item->events()->oldest('id')->first();
    }

    public function hasEventAfter(FormalCorrespondence $item, int $eventId): bool
    {
        return $item->events()->where('id', '>', $eventId)->exists();
    }

    public function nextBookSequence(): int
    {
        return FormalCorrespondenceEvent::whereDate('created_at', today())->whereNotNull('book_number')->count() + 1;
    }

    public function createEventResponse(FormalCorrespondenceEvent $event, array $attributes): void
    {
        $event->responses()->create($attributes);
    }

    public function createDocuments(FormalCorrespondence $item, array $rows): void
    {
        foreach ($rows as $row) {
            $item->documents()->create($row);
        }
    }

    public function findDocument(int $documentId): FormalCorrespondenceDocument
    {
        return FormalCorrespondenceDocument::with('event.formalCorrespondence')->findOrFail($documentId);
    }

    public function updateDocument(FormalCorrespondenceDocument $document, array $attributes): void
    {
        $document->update($attributes);
    }

    public function recipientIds(FormalCorrespondenceEvent $event, int $exceptActorId): BaseCollection
    {
        return $this->recipientQuery($event)->where('id', '!=', $exceptActorId)->pluck('id');
    }

    public function assignableUser(FormalCorrespondenceEvent $event, int $userId): ?User
    {
        return $this->assignableQuery($event)->whereKey($userId)->first();
    }

    /** رئيس الجهة حسب نوعها؛ يُستعمل عندما لا يكون لكاتب المعالجة حساب. */
    public function partyHead(?string $type, $id): ?User
    {
        return match ($type) {
            'general_manager' => $id
                ? User::whereKey($id)->first()
                : User::where('role', 'general_manager')->where('is_active', true)->first(),
            'branch' => User::where('role', 'branch_manager')->where('branch_id', $id)->where('is_active', true)->first(),
            'department' => User::where('role', 'department_head')->where('department_id', $id)->where('is_active', true)->first(),
            'office' => User::where('office_id', $id)->where('is_active', true)
                ->orderByRaw("CASE role WHEN 'general_manager' THEN 1 WHEN 'branch_manager' THEN 2 WHEN 'department_head' THEN 3 ELSE 4 END")
                ->first(),
            'diwan' => $this->diwanQuery()->first(),
            'user' => User::whereKey($id)->first(),
            default => null,
        };
    }

    public function eventActor(FormalCorrespondenceEvent $event): ?User
    {
        if (! $event->actor_id) {
            return null;
        }

        return $event->relationLoaded('actor') ? $event->actor : $event->actor()->first();
    }

    public function officeOf(User $user): ?Office
    {
        return $user->relationLoaded('office') ? $user->office : $user->office()->first(['code', 'name']);
    }

    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    /** من يُشعَر بوصول المعالجة: رئيس الجهة الهدف أو أعضاؤها. */
    private function recipientQuery(FormalCorrespondenceEvent $event): Builder
    {
        return match ($event->target_type) {
            'diwan' => $this->diwanQuery(),
            'general_manager' => User::query()->where('is_active', true)->where('role', 'general_manager')
                ->when($event->target_id, fn (Builder $query) => $query->whereKey($event->target_id)),
            'branch' => User::query()->where('is_active', true)->where('role', 'branch_manager')->where('branch_id', $event->target_id),
            'department' => User::query()->where('is_active', true)->where('role', 'department_head')->where('department_id', $event->target_id),
            'office' => User::query()->where('is_active', true)->where('office_id', $event->target_id),
            'user' => User::query()->where('is_active', true)->whereKey($event->target_id),
            default => User::query()->whereRaw('1 = 0'),
        };
    }

    /** من يصلح للتخويل: أي عضو في الجهة الهدف، لا رئيسها وحده. */
    private function assignableQuery(FormalCorrespondenceEvent $event): Builder
    {
        return match ($event->target_type) {
            'diwan' => $this->diwanQuery(),
            'general_manager' => User::query()->where('is_active', true)->where('role', 'general_manager'),
            'branch' => User::query()->where('is_active', true)->where('branch_id', $event->target_id),
            'department' => User::query()->where('is_active', true)->where('department_id', $event->target_id),
            'office' => User::query()->where('is_active', true)->where('office_id', $event->target_id),
            'user' => User::query()->where('is_active', true)->whereKey($event->target_id),
            default => User::query()->whereRaw('1 = 0'),
        };
    }

    private function diwanQuery(): Builder
    {
        return User::query()->where('is_active', true)->whereHas('office', fn (Builder $query) => $query
            ->where('code', 'REGISTRY')
            ->orWhere('name', 'like', '%ديوان%')
            ->orWhere('name', 'like', '%السجل%'));
    }
}
