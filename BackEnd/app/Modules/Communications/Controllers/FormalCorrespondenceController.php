<?php
namespace App\Modules\Communications\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FormalCorrespondence;
use App\Models\FormalCorrespondenceEvent;
use App\Modules\Communications\Repositories\Interfaces\FormalCorrespondenceRepositoryInterface;
use App\Modules\Communications\Requests\StoreFormalCorrespondenceEventRequest;
use App\Modules\Communications\Requests\StoreFormalCorrespondenceRequest;
use App\Modules\Communications\Requests\UpdateFormalCorrespondenceEventRequest;
use App\Modules\Communications\Services\FormalDocumentService;
use App\Modules\Communications\Services\FormalPartyResolver;
use App\Modules\Communications\Services\FormalTimelineService;
use App\Modules\Communications\Services\FormalVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormalCorrespondenceController extends Controller
{
    public function __construct(
        private FormalPartyResolver $parties,
        private FormalVisibilityService $visibility,
        private FormalTimelineService $timeline,
        private FormalDocumentService $documents,
        private FormalCorrespondenceRepositoryInterface $formal,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // visibleFor يمرّ على الجميع كي تُختم صلاحيات التعديل حتى للديوان والمدير العام.
        $items = $this->formal->all()
            ->map(fn (FormalCorrespondence $item) => $this->visibility->visibleFor($item, $user));

        if (! $this->visibility->canViewFullThread($user)) {
            $items = $items
                ->filter(fn (FormalCorrespondence $item) => $item->visible_events_count > 0)
                ->values();
        }

        return response()->json($items);
    }

    public function directory(): JsonResponse
    {
        return response()->json([
            'external_entities' => $this->formal->externalEntities(),
            'branches' => $this->formal->branchesWithDepartments(),
            'offices' => $this->formal->activeOffices(),
            'general_managers' => $this->formal->generalManagers(),
            'staff' => $this->formal->activeStaff(),
            'correspondences' => $this->formal->recentCorrespondences(500),
            'places' => $this->parties->knownPlaces(),
        ]);
    }

    public function store(StoreFormalCorrespondenceRequest $request): JsonResponse
    {
        abort_unless(
            $this->visibility->canCreate($request->user()),
            403,
            'إنشاء المراسلات متاح للديوان ومدير قواعد البيانات فقط.'
        );

        $data = $request->validated();

        $correspondence = $this->formal->transaction(function () use ($request, $data) {
            ['source' => $source, 'target' => $target] = $this->parties->partiesForDirection($data['direction'], $data);

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $data['attachment_path'] = $file->store('formal-correspondences/incoming', 'public');
                $data['attachment_name'] = $file->getClientOriginalName();
            }

            $item = $this->formal->create([
                'reference_code' => $this->nextReference(),
                'parent_id' => $data['parent_id'] ?? null,
                'direction' => $data['direction'],
                'status' => 'open',
                'subject' => $data['subject'],
                'summary' => $data['summary'] ?? null,
                'body' => $data['body'] ?? null,
                'creator_id' => $request->user()->id,
                'source_type' => $source['type'],
                'source_id' => $source['id'],
                'source_label' => $source['label'],
                'target_type' => $target['type'],
                'target_id' => $target['id'],
                'target_label' => $target['label'],
                ...$this->parties->legacyColumns($data['direction'], $source, $target),
                'first_place' => $data['first_place'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
                'attachment_name' => $data['attachment_name'] ?? null,
                'issued_at' => $data['issued_at'] ?? null,
                'qr_payload' => 'CND-FORMAL:' . ($data['subject'] ?? ''),
            ]);

            // كل المراسلات تبدأ عند الديوان ولا تظهر لغيره حتى يحوّلها.
            $firstTarget = $this->parties->resolve('diwan', null);

            $this->timeline->addEvent($item, $request->user()->id, [
                'event' => 'created',
                'source' => $source,
                'target' => $firstTarget,
                'note' => $data['first_note'] ?? 'تسجيل المراسلة',
                'date' => $data['issued_at'] ?? null,
                'status' => 'open',
                'action_required' => 'route',
            ]);

            return $item;
        });

        return response()->json($this->visibility->visibleLoaded($correspondence, $request->user()), 201);
    }

    public function update(Request $request, FormalCorrespondence $formalCorrespondence): JsonResponse
    {
        abort_unless(
            $this->visibility->canEditCorrespondence($request->user(), $formalCorrespondence),
            403,
            'لا يمكن تعديل المراسلة بعد أن تبدأ الجهة التالية بمعالجتها.'
        );

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:formal_correspondences,id', 'different:id'],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,png,jpg,jpeg'],
        ]);

        $attributes = collect($data)->only(['subject', 'summary', 'body'])->all();

        if (array_key_exists('parent_id', $data) && (int) ($data['parent_id'] ?? 0) !== $formalCorrespondence->id) {
            $attributes['parent_id'] = $data['parent_id'] ?: null;
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attributes['attachment_path'] = $file->store('formal-correspondences/incoming', 'public');
            $attributes['attachment_name'] = $file->getClientOriginalName();
        }

        $this->formal->update($formalCorrespondence, $attributes);

        return response()->json($this->visibility->visibleLoaded($formalCorrespondence->fresh(), $request->user()));
    }

    public function destroy(Request $request, FormalCorrespondence $formalCorrespondence): JsonResponse
    {
        abort_unless($this->visibility->canDelete($request->user()), 403, 'لا تملك صلاحية حذف المراسلات.');

        $this->formal->delete($formalCorrespondence);

        return response()->json(['message' => 'deleted']);
    }

    /** المرفقات فقط؛ الكتاب صار جزءاً من المعالجة نفسها. */
    public function updateDocument(Request $request, $documentId): JsonResponse
    {
        $document = $this->formal->findDocument((int) $documentId);
        abort_unless(
            $document->event && $this->visibility->canEditEvent($request->user(), $document->event),
            403,
            'لا يمكن تعديل المرفق بعد أن تبدأ الجهة التالية بمعالجته.'
        );

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:180'],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,png,jpg,jpeg'],
        ]);

        if (! empty($data['title'])) {
            $this->formal->updateDocument($document, ['title' => $data['title']]);
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $this->formal->updateDocument($document, [
                'attachment_path' => $file->store('formal-correspondences/documents', 'public'),
                'attachment_name' => $file->getClientOriginalName(),
                'attachment_mime' => $file->getClientMimeType(),
                'attachment_size' => $file->getSize(),
            ]);
        }

        return response()->json($this->visibility->visibleLoaded($document->event->formalCorrespondence->fresh(), $request->user()));
    }

    /** إضافة معالجة: الجهة المصدرة تُشتق من الجهة التي تحتفظ بالمراسلة ولا تُقبل من الواجهة. */
    public function addEvent(StoreFormalCorrespondenceEventRequest $request, FormalCorrespondence $formalCorrespondence): JsonResponse
    {
        $holder = $this->parties->currentHolder($formalCorrespondence);
        abort_unless($this->visibility->canProcess($request->user(), $formalCorrespondence), 403, 'المراسلة ليست لدى جهتك حالياً.');

        $data = $request->validated();
        $target = $this->parties->resolve($data['target_type'] ?? 'free_text', $data['target_id'] ?? null, $data['place'] ?? null);
        $latestEvent = $this->formal->latestEvent($formalCorrespondence);
        $source = $holder;

        // عند تخويل موظف تصبح صفته/مكتبه هي الجهة المصدرة للمعالجة ولا تُقبل من الواجهة.
        if ($latestEvent?->assigned_user_id === $request->user()->id) {
            $user = $request->user()->loadMissing('office');
            $source = [
                'type' => 'user',
                'id' => $user->id,
                'label' => $user->office?->name ?: $user->job_title ?: $user->name,
            ];
        }

        $this->formal->transaction(function () use ($request, $formalCorrespondence, $data, $source, $target) {
            $event = $this->timeline->addEvent($formalCorrespondence, $request->user()->id, [
                'event' => 'route_step',
                'source' => $source,
                'target' => $target,
                'note' => $data['note'] ?? null,
                'date' => $data['date'] ?? null,
                'status' => $data['status'] ?? $formalCorrespondence->status,
                'action_required' => $data['action_required'] ?? null,
            ]);

            $this->documents->buildProcessingLetter($event, $data, $request->user());
            $this->documents->storeEventAttachments($request, $formalCorrespondence, $event);

            if (! empty($data['status'])) {
                $this->formal->update($formalCorrespondence, ['status' => $data['status']]);
            }
        });

        return response()->json($this->visibility->visibleLoaded($formalCorrespondence->fresh(), $request->user()));
    }

    public function updateEvent(UpdateFormalCorrespondenceEventRequest $request, FormalCorrespondenceEvent $event): JsonResponse
    {
        abort_unless(
            $this->visibility->canEditEvent($request->user(), $event),
            403,
            'لا يمكن تعديل المعالجة بعد أن تبدأ الجهة التالية بمعالجتها.'
        );

        $data = $request->validated();
        $target = $this->parties->resolve($data['target_type'] ?? $event->target_type ?? 'free_text', $data['target_id'] ?? $event->target_id, $data['place'] ?? null);
        $meta = $event->meta ?? [];
        $meta['place'] = $target['label'];
        $meta['target_label'] = $target['label'];

        $this->formal->updateEvent($event, [
            'note' => $data['note'] ?? null,
            'to_status' => $data['status'] ?? $event->to_status,
            'target_type' => $target['type'],
            'target_id' => $target['id'],
            'target_label' => $target['label'],
            'action_required' => $data['action_required'] ?? $event->action_required,
            'meta' => $meta,
        ]);

        $correspondence = $event->formalCorrespondence;

        if (! empty($data['status'])) {
            $this->formal->update($correspondence, ['status' => $data['status']]);
        }

        $this->documents->buildProcessingLetter($event->fresh(), $data, $request->user());
        $this->documents->storeEventAttachments($request, $correspondence, $event);

        return response()->json($this->visibility->visibleLoaded($correspondence->fresh(), $request->user()));
    }

    public function decide(Request $request, FormalCorrespondenceEvent $event): JsonResponse
    {
        abort_unless($this->visibility->canAccessEvent($request->user(), $event) && $request->user()?->role === 'general_manager', 403, 'لا تملك صلاحية تسجيل قرار هذه المعالجة.');

        $data = $request->validate([
            'decision_type' => ['required', 'in:reply,route_internal,hold'],
            'target_type' => ['nullable', 'in:branch,department,office,external_entity,general_manager,diwan,free_text'],
            'target_id' => ['nullable', 'integer'],
            'place' => ['nullable', 'string', 'max:180'],
            'note' => ['nullable', 'string', 'max:1000'],
            'document_title' => ['nullable', 'string', 'max:180'],
            'document_body' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,png,jpg,jpeg'],
        ]);

        $correspondence = $event->formalCorrespondence;

        if ($data['decision_type'] === 'hold') {
            $this->formal->updateEvent($event, [
                'decision_type' => 'hold',
                'decision_status' => 'pending',
                'action_required' => 'hold',
                'note' => $data['note'] ?? $event->note,
            ]);
            $this->formal->update($correspondence, ['status' => 'open']);

            return response()->json($this->visibility->visibleLoaded($correspondence->fresh(), $request->user()));
        }

        $targetType = $data['decision_type'] === 'reply' ? 'external_entity' : ($data['target_type'] ?? 'free_text');
        $target = $this->parties->resolve($targetType, $data['target_id'] ?? null, $data['place'] ?? 'جهة خارجية');
        $status = $data['decision_type'] === 'reply' ? 'archived' : 'routed';

        $this->formal->updateEvent($event, ['decision_type' => $data['decision_type'], 'decision_status' => 'completed', 'responded_at' => now()]);

        $newEvent = $this->timeline->addEvent($correspondence, $request->user()->id, [
            'event' => $data['decision_type'] === 'reply' ? 'external_reply' : 'route_step',
            'source' => ['type' => $event->target_type, 'id' => $event->target_id, 'label' => $event->target_label ?: 'المدير العام'],
            'target' => $target,
            'note' => $data['note'] ?? null,
            'status' => $status,
            'action_required' => $data['decision_type'] === 'reply' ? 'reply' : null,
            'decision_type' => $data['decision_type'],
        ]);

        $this->documents->buildProcessingLetter($newEvent, $data, $request->user());
        $this->documents->storeEventAttachments($request, $correspondence, $newEvent);

        $this->formal->update($correspondence, ['status' => $status]);

        return response()->json($this->visibility->visibleLoaded($correspondence->fresh(), $request->user()));
    }

    public function respond(Request $request, FormalCorrespondenceEvent $event): JsonResponse
    {
        abort_unless($this->visibility->canAccessEvent($request->user(), $event), 403, 'لا تملك صلاحية الرد على هذه المعالجة.');

        $data = $request->validate([
            'response_type' => ['required', 'in:study,execution'],
            'body' => ['required', 'string'],
            'recommendation' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,png,jpg,jpeg'],
        ]);

        $payload = [
            'actor_id' => $request->user()->id,
            'response_type' => $data['response_type'],
            'body' => $data['body'],
            'recommendation' => $data['recommendation'] ?? null,
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $payload += [
                'attachment_path' => $file->store('formal-correspondences/responses', 'public'),
                'attachment_name' => $file->getClientOriginalName(),
                'attachment_mime' => $file->getClientMimeType(),
                'attachment_size' => $file->getSize(),
            ];
        }

        $this->formal->createEventResponse($event, $payload);
        $this->formal->updateEvent($event, ['decision_status' => 'responded', 'responded_at' => now()]);

        return response()->json($this->visibility->visibleLoaded($event->formalCorrespondence->fresh(), $request->user()));
    }

    /** تخويل موظف من داخل الجهة الهدف بإدارة المعالجة. */
    public function assign(Request $request, FormalCorrespondenceEvent $event): JsonResponse
    {
        abort_unless($this->visibility->canAssignStage($request->user(), $event), 403, 'التخويل متاح لرئيس الجهة التي وصلتها المعالجة فقط.');

        $data = $request->validate(['assigned_user_id' => ['required', 'integer', 'exists:users,id']]);

        $assignee = $this->formal->assignableUser($event, (int) $data['assigned_user_id']);
        abort_unless($assignee, 422, 'يجب أن يكون الموظف المخوَّل من داخل الجهة نفسها.');

        $this->timeline->assign($event, $assignee, $request->user());

        return response()->json($this->visibility->visibleLoaded($event->formalCorrespondence->fresh(), $request->user()));
    }

    public function destroyEvent(Request $request, FormalCorrespondenceEvent $event): JsonResponse
    {
        abort_unless($this->visibility->canDelete($request->user()), 403, 'لا تملك صلاحية حذف المعالجات.');

        $correspondence = $event->formalCorrespondence;
        $this->formal->deleteEvent($event);

        return response()->json($this->visibility->visibleLoaded($correspondence->fresh(), $request->user()));
    }

    /** إضافة مرفقات إضافية إلى معالجة قائمة. */
    public function addEventDocument(Request $request, FormalCorrespondenceEvent $event): JsonResponse
    {
        abort_unless($this->visibility->canEditEvent($request->user(), $event), 403, 'لا تملك صلاحية إضافة مرفقات لهذه المعالجة.');

        $request->validate([
            'attachments' => ['required', 'array'],
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,png,jpg,jpeg'],
        ]);

        $correspondence = $event->formalCorrespondence;
        $this->documents->storeEventAttachments($request, $correspondence, $event);

        return response()->json($this->visibility->visibleLoaded($correspondence->fresh(), $request->user()));
    }

    private function nextReference(): string
    {
        return 'CND-COR-' . now()->format('Ymd') . '-' . str_pad((string) $this->formal->nextDailySequence(), 4, '0', STR_PAD_LEFT);
    }
}
