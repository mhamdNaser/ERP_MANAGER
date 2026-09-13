<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\ExternalEntity;
use App\Models\FormalCorrespondence;
use App\Models\FormalCorrespondenceDocument;
use App\Models\FormalCorrespondenceEvent;
use App\Models\Office;
use App\Models\User;
use App\Services\Documents\DocxTemplateRenderer;
use App\Services\Documents\SimpleQrImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class FormalCorrespondenceSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::query()
            ->whereIn('role', ['database_manager', 'general_manager', 'branch_manager'])
            ->orderByRaw("CASE role WHEN 'database_manager' THEN 1 WHEN 'general_manager' THEN 2 ELSE 3 END")
            ->first();

        if (! $creator) {
            return;
        }

        $branch = Branch::query()->where('code', 'DAM')->first() ?? Branch::query()->first();
        $department = Department::query()->where('code', 'INFRA')->first() ?? Department::query()->first();

        $ministry = ExternalEntity::firstOrCreate(
            ['name' => 'وزارة الاتصالات والتحول الرقمي'],
            ['code' => 'MCTD', 'contact_name' => 'مديرية التنسيق', 'contact_email' => 'coordination@mctd.local', 'contact_phone' => '011-000000']
        );

        $provider = ExternalEntity::firstOrCreate(
            ['name' => 'الشركة السورية للاتصالات'],
            ['code' => 'SY-TELECOM', 'contact_name' => 'مكتب المتابعة الفنية', 'contact_phone' => '011-111111']
        );

        $partner = ExternalEntity::firstOrCreate(
            ['name' => 'مركز البيانات الوطني'],
            ['code' => 'NDC', 'contact_name' => 'قسم الربط الحكومي']
        );

        $incoming = FormalCorrespondence::firstOrCreate(
            ['reference_code' => 'CND-COR-SEED-0001'],
            [
                'direction' => 'external_to_internal',
                'status' => 'routed',
                'subject' => 'تحديث مسار الربط الحكومي',
                'summary' => 'مراسلة واردة بخصوص تحديث مسار الربط بين الجهات الحكومية وتوثيق الجهات التي مرت عليها قبل وصولها للمؤسسة.',
                'creator_id' => $creator->id,
                'sender_external_entity_id' => $ministry->id,
                'recipient_branch_id' => $branch?->id,
                'recipient_department_id' => $department?->id,
                'source_type' => 'external_entity',
                'source_id' => $ministry->id,
                'source_label' => $ministry->name,
                'target_type' => 'our_org',
                'target_label' => 'مؤسستنا',
                'issued_at' => now()->subDays(6),
                'qr_payload' => 'CND-FORMAL:CND-COR-SEED-0001',
            ]
        );

        if ($incoming->events()->count() === 0) {
            $origin = $incoming->events()->create([
                'actor_id' => $creator->id,
                'event' => 'created',
                'to_status' => 'open',
                'note' => 'صدرت المراسلة من الوزارة وتم تسجيلها كأصل السلسلة.',
                'meta' => ['place' => $ministry->name, 'date' => now()->subDays(6)->toDateString()],
                'created_at' => now()->subDays(6),
            ]);

            $origin->documents()->create([
                'formal_correspondence_id' => $incoming->id,
                'document_type' => 'uploaded',
                'title' => 'كتاب الوزارة رقم 45/ر',
                'source_label' => $ministry->name,
                'target_label' => $provider->name,
                'body' => 'يمثل هذا السجل الملف الأصلي الوارد من الجهة الخارجية، ويمكن استبداله لاحقاً بملف PDF مرفوع.',
            ]);

            $providerStep = $incoming->events()->create([
                'actor_id' => $creator->id,
                'event' => 'route_step',
                'to_status' => 'routed',
                'note' => 'تمت إحالة المراسلة إلى مزود الخدمة لإضافة الرأي الفني.',
                'meta' => ['place' => $provider->name, 'date' => now()->subDays(4)->toDateString()],
                'created_at' => now()->subDays(4),
            ]);

            $providerStep->documents()->create([
                'formal_correspondence_id' => $incoming->id,
                'document_type' => 'uploaded',
                'title' => 'رد فني من الشركة السورية للاتصالات',
                'source_label' => $provider->name,
                'target_label' => 'مؤسستنا',
                'body' => 'وثيقة رد فني ضمن السلسلة تبين أن كل محطة يمكن أن تضيف ملفها الخاص.',
            ]);

            $internalStep = $incoming->events()->create([
                'actor_id' => $creator->id,
                'event' => 'route_step',
                'to_status' => 'routed',
                'note' => 'دخلت المراسلة إلى المؤسسة وتم إعداد كتاب إحالة داخلي لقسم البنية التحتية.',
                'meta' => ['place' => 'مؤسستنا - الديوان', 'date' => now()->subDays(2)->toDateString()],
                'created_at' => now()->subDays(2),
            ]);

            $internalStep->documents()->create([
                'formal_correspondence_id' => $incoming->id,
                'document_type' => 'internal_letter',
                'title' => 'كتاب إحالة داخلي',
                'source_label' => 'مؤسستنا - الديوان',
                'target_label' => $department?->name ?: 'القسم المختص',
                'body' => 'يرجى دراسة مضمون المراسلة الواردة وإعداد المقترح الفني للرد ضمن المهلة المحددة.',
            ]);
        }

        $outgoing = FormalCorrespondence::firstOrCreate(
            ['reference_code' => 'CND-COR-SEED-0002'],
            [
                'direction' => 'internal_to_external',
                'status' => 'open',
                'subject' => 'طلب تأكيد جاهزية الربط الاحتياطي',
                'summary' => 'نموذج مراسلة صادرة عن المؤسسة، يبدأ بكتاب داخلي ويمكن لاحقاً توليده من قالب Word/PDF.',
                'creator_id' => $creator->id,
                'recipient_external_entity_id' => $partner->id,
                'recipient_branch_id' => $branch?->id,
                'source_type' => 'branch',
                'source_id' => $branch?->id,
                'source_label' => $branch?->name ?: 'مؤسستنا',
                'target_type' => 'external_entity',
                'target_id' => $partner->id,
                'target_label' => $partner->name,
                'issued_at' => now()->subDay(),
                'qr_payload' => 'CND-FORMAL:CND-COR-SEED-0002',
            ]
        );

        if ($outgoing->events()->count() === 0) {
            $draft = $outgoing->events()->create([
                'actor_id' => $creator->id,
                'event' => 'created',
                'to_status' => 'open',
                'note' => 'تم إنشاء كتاب صادر عن المؤسسة كجزء من سلسلة المراسلة.',
                'meta' => ['place' => 'مؤسستنا', 'date' => now()->subDay()->toDateString()],
                'created_at' => now()->subDay(),
            ]);

            $draft->documents()->create([
                'formal_correspondence_id' => $outgoing->id,
                'document_type' => 'internal_letter',
                'title' => 'كتاب طلب تأكيد الجاهزية',
                'source_label' => 'مؤسستنا',
                'target_label' => $partner->name,
                'body' => 'نرجو تزويدنا بتأكيد جاهزية مسار الربط الاحتياطي، مع تحديد نقاط الاتصال الفنية المعتمدة.',
            ]);
        }

        $this->seedProfessionalDemoThreads($creator, $branch, $department, $ministry, $provider, $partner);
        $this->regenerateSeedInternalLetters($creator);
    }

    private function seedProfessionalDemoThreads(
        User $creator,
        ?Branch $branch,
        ?Department $department,
        ExternalEntity $ministry,
        ExternalEntity $provider,
        ExternalEntity $partner,
    ): void {
        FormalCorrespondence::whereIn('reference_code', [
            'CND-COR-DEMO-0001',
            'CND-COR-DEMO-0002',
            'CND-COR-DEMO-0003',
        ])->delete();

        $registry = Office::firstOrCreate(['code' => 'REGISTRY'], ['name' => 'الديوان', 'is_active' => true]);
        $general = User::where('role', 'general_manager')->first() ?: $creator;
        $departmentHead = User::where('role', 'department_head')->where('department_id', $department?->id)->first() ?: $creator;
        $branchManager = User::where('role', 'branch_manager')->where('branch_id', $branch?->id)->first() ?: $creator;

        $incoming = FormalCorrespondence::create([
            'reference_code' => 'CND-COR-DEMO-0001',
            'direction' => 'external_to_internal',
            'status' => 'routed',
            'subject' => 'Demo - واردة للديوان ثم قرار المدير ثم دراسة القسم',
            'summary' => 'سلسلة تجريبية كاملة توضح استلام الديوان، تحويل المدير العام، كتاب داخلي مولد من قالب Word، ورد دراسة من القسم.',
            'creator_id' => $creator->id,
            'sender_external_entity_id' => $ministry->id,
            'recipient_branch_id' => $branch?->id,
            'recipient_department_id' => $department?->id,
            'source_type' => 'external_entity',
            'source_id' => $ministry->id,
            'source_label' => $ministry->name,
            'target_type' => 'our_org',
            'target_label' => 'مؤسستنا',
            'first_place' => 'الديوان',
            'issued_at' => now()->subDays(5),
            'qr_payload' => 'CND-FORMAL:CND-COR-DEMO-0001',
        ]);

        $received = $this->stage($incoming, $creator, [
            'event' => 'created',
            'source_type' => 'external_entity',
            'source_id' => $ministry->id,
            'source_label' => $ministry->name,
            'target_type' => 'diwan',
            'target_label' => 'الديوان',
            'action_required' => 'route',
            'note' => 'استلم الديوان الكتاب الوارد من الجهة الخارجية وسجله كنقطة بداية داخل المؤسسة.',
            'created_at' => now()->subDays(5),
        ]);
        $this->uploadedPdf($incoming, $received, 'الكتاب الخارجي الوارد رقم 420/و', $ministry->name, 'الديوان', 'demo-incoming-420.pdf');

        $toGeneral = $this->stage($incoming, $creator, [
            'event' => 'route_step',
            'source_type' => 'diwan',
            'source_label' => 'الديوان',
            'target_type' => 'general_manager',
            'target_id' => $general->id,
            'target_label' => $general->name,
            'action_required' => 'decision',
            'note' => 'حوّل الديوان المراسلة إلى المدير العام لاتخاذ قرار التعامل.',
            'created_at' => now()->subDays(4),
        ]);
        $this->internalLetter($incoming, $toGeneral, [
            'title' => 'كتاب إحالة إلى المدير العام',
            'registry_number' => '420/و',
            'book_number' => 'CND-BOOK-DEMO-0001',
            'source_label' => 'الديوان',
            'target_label' => 'إلى السيد المدير العام',
            'body' => 'يرجى الاطلاع على الكتاب الوارد واتخاذ القرار المناسب بخصوص معالجة طلب الجهة الخارجية ضمن سلسلة المراسلة.',
        ], $creator);

        $toDepartment = $this->stage($incoming, $general, [
            'event' => 'route_step',
            'source_type' => 'general_manager',
            'source_id' => $general->id,
            'source_label' => $general->name,
            'target_type' => 'department',
            'target_id' => $department?->id,
            'target_label' => $department?->name ?: 'قسم البنية التحتية',
            'action_required' => 'study',
            'decision_type' => 'route_internal',
            'decision_status' => 'responded',
            'responded_at' => now()->subDays(2),
            'note' => 'قرار المدير العام: تحويل المراسلة إلى القسم المختص لإعداد دراسة فنية.',
            'created_at' => now()->subDays(3),
        ]);
        $this->internalLetter($incoming, $toDepartment, [
            'title' => 'كتاب إحالة داخلي للدراسة',
            'registry_number' => '421/د',
            'book_number' => 'CND-BOOK-DEMO-0002',
            'source_label' => $general->name,
            'target_label' => 'إلى السيد ' . ($department?->name ?: 'قسم البنية التحتية'),
            'body' => 'يرجى دراسة مضمون المراسلة الواردة وإعداد المقترح الفني للرد ضمن المهلة المحددة، مع بيان أثر الطلب على مسارات الربط الحالية.',
        ], $general);
        $toDepartment->responses()->create([
            'actor_id' => $departmentHead->id,
            'response_type' => 'study',
            'body' => 'تمت دراسة الطلب وتبين أن الربط المقترح ممكن فنياً بشرط اعتماد مسار احتياطي ومراجعة جدول العناوين.',
            'recommendation' => 'نوصي بالموافقة المشروطة وإرسال رد رسمي للجهة الخارجية.',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $toBranch = $this->stage($incoming, $general, [
            'event' => 'route_step',
            'source_type' => 'general_manager',
            'source_id' => $general->id,
            'source_label' => $general->name,
            'target_type' => 'branch',
            'target_id' => $branch?->id,
            'target_label' => $branch?->name ?: 'فرع دمشق',
            'action_required' => 'execution',
            'decision_type' => 'route_internal',
            'decision_status' => 'responded',
            'responded_at' => now()->subDay(),
            'note' => 'تحويل تجريبي لفرع داخلي لتنفيذ الإجراء بعد الدراسة.',
            'created_at' => now()->subDays(2),
        ]);
        $toBranch->responses()->create([
            'actor_id' => $branchManager->id,
            'response_type' => 'execution',
            'body' => 'تم حجز نافذة تنفيذ أولية والتأكد من جاهزية نقاط الربط في الفرع.',
            'recommendation' => 'بانتظار الكتاب الخارجي النهائي قبل التنفيذ الفعلي.',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $reply = FormalCorrespondence::create([
            'reference_code' => 'CND-COR-DEMO-0002',
            'direction' => 'internal_to_external',
            'status' => 'closed',
            'subject' => 'Demo - قرار رد خارجي وإنهاء السلسلة',
            'summary' => 'سلسلة تجريبية مختصرة توضح قرار المدير العام بالرد وكتاب الرد المولد من قالب Word.',
            'creator_id' => $creator->id,
            'sender_external_entity_id' => null,
            'recipient_external_entity_id' => $partner->id,
            'source_type' => 'general_manager',
            'source_id' => $general->id,
            'source_label' => $general->name,
            'target_type' => 'external_entity',
            'target_id' => $partner->id,
            'target_label' => $partner->name,
            'first_place' => 'المدير العام',
            'issued_at' => now()->subDays(3),
            'qr_payload' => 'CND-FORMAL:CND-COR-DEMO-0002',
        ]);
        $replyDecision = $this->stage($reply, $general, [
            'event' => 'route_step',
            'source_type' => 'general_manager',
            'source_id' => $general->id,
            'source_label' => $general->name,
            'target_type' => 'external_entity',
            'target_id' => $partner->id,
            'target_label' => $partner->name,
            'action_required' => 'reply',
            'decision_type' => 'reply',
            'decision_status' => 'responded',
            'responded_at' => now()->subDays(2),
            'note' => 'قرار المدير العام: إعداد رد خارجي وإنهاء سلسلة المعالجة داخل المؤسسة.',
            'created_at' => now()->subDays(3),
        ]);
        $this->internalLetter($reply, $replyDecision, [
            'title' => 'كتاب رد خارجي',
            'registry_number' => '515/ص',
            'book_number' => 'CND-BOOK-DEMO-0003',
            'source_label' => 'مؤسستنا',
            'target_label' => 'إلى السيد ' . $partner->name,
            'body' => 'إشارة إلى طلبكم المتعلق بجاهزية الربط الاحتياطي، نعلمكم بالموافقة المبدئية على المتابعة وفق الشروط الفنية المرفقة.',
        ], $general);

        $hold = FormalCorrespondence::create([
            'reference_code' => 'CND-COR-DEMO-0003',
            'direction' => 'external_to_internal',
            'status' => 'on_hold',
            'subject' => 'Demo - قرار تريث بانتظار استكمال معلومات',
            'summary' => 'سلسلة تجريبية تظهر حالة التريث بدون كتاب صادر نهائي.',
            'creator_id' => $creator->id,
            'sender_external_entity_id' => $provider->id,
            'source_type' => 'external_entity',
            'source_id' => $provider->id,
            'source_label' => $provider->name,
            'target_type' => 'our_org',
            'target_label' => 'مؤسستنا',
            'first_place' => 'الديوان',
            'issued_at' => now()->subDays(2),
            'qr_payload' => 'CND-FORMAL:CND-COR-DEMO-0003',
        ]);
        $holdStage = $this->stage($hold, $general, [
            'event' => 'route_step',
            'source_type' => 'diwan',
            'source_label' => 'الديوان',
            'target_type' => 'general_manager',
            'target_id' => $general->id,
            'target_label' => $general->name,
            'action_required' => 'decision',
            'decision_type' => 'hold',
            'decision_status' => 'pending',
            'note' => 'قرار تجريبي: التريث ريثما تصل نسخة أوضح عن المرفق الخارجي.',
            'created_at' => now()->subDays(2),
        ]);
        $this->uploadedPdf($hold, $holdStage, 'مرفق خارجي يحتاج استكمال', $provider->name, $general->name, 'demo-hold-needs-followup.pdf');
    }

    private function regenerateSeedInternalLetters(User $signer): void
    {
        FormalCorrespondenceDocument::query()
            ->where('document_type', 'internal_letter')
            ->whereHas('formalCorrespondence', fn ($query) => $query->where('reference_code', 'like', 'CND-COR-SEED-%'))
            ->get()
            ->each(function (FormalCorrespondenceDocument $document) use ($signer) {
                if (! $document->registry_number) {
                    $document->registry_number = 'تجريبي';
                }

                if (! $document->book_number) {
                    $document->book_number = 'CND-BOOK-SEED-' . str_pad((string) $document->id, 4, '0', STR_PAD_LEFT);
                }

                if (! $document->qr_payload) {
                    $document->qr_payload = 'CND-BOOK:' . $document->book_number;
                }

                $document->save();
                $this->renderInternalLetter($document, $signer);
            });
    }

    private function stage(FormalCorrespondence $item, User $actor, array $data): FormalCorrespondenceEvent
    {
        $createdAt = $data['created_at'] ?? now();

        return $item->events()->create([
            'actor_id' => $actor->id,
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'source_label' => $data['source_label'] ?? null,
            'target_type' => $data['target_type'] ?? null,
            'target_id' => $data['target_id'] ?? null,
            'target_label' => $data['target_label'] ?? null,
            'action_required' => $data['action_required'] ?? null,
            'decision_type' => $data['decision_type'] ?? null,
            'decision_status' => $data['decision_status'] ?? null,
            'responded_at' => $data['responded_at'] ?? null,
            'event' => $data['event'] ?? 'route_step',
            'to_status' => $data['to_status'] ?? 'routed',
            'note' => $data['note'] ?? null,
            'meta' => [
                'place' => $data['target_label'] ?? $data['place'] ?? 'غير محددة',
                'date' => $createdAt->toDateString(),
                'source_label' => $data['source_label'] ?? null,
                'target_label' => $data['target_label'] ?? null,
            ],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function uploadedPdf(FormalCorrespondence $item, FormalCorrespondenceEvent $event, string $title, string $source, string $target, string $fileName): void
    {
        $path = 'formal-correspondences/demo/' . $fileName;
        Storage::disk('public')->put($path, $this->samplePdf($title));

        $event->documents()->create([
            'formal_correspondence_id' => $item->id,
            'document_type' => 'uploaded',
            'title' => $title,
            'source_label' => $source,
            'target_label' => $target,
            'body' => 'ملف PDF تجريبي مرفق لإظهار معاينة الكتب الخارجية ضمن تفاصيل المرحلة.',
            'attachment_path' => $path,
            'attachment_name' => $fileName,
            'attachment_mime' => 'application/pdf',
            'attachment_size' => Storage::disk('public')->size($path),
        ]);
    }

    private function internalLetter(FormalCorrespondence $item, FormalCorrespondenceEvent $event, array $data, User $signer): FormalCorrespondenceDocument
    {
        $document = $event->documents()->create([
            'formal_correspondence_id' => $item->id,
            'document_type' => 'internal_letter',
            'title' => $data['title'],
            'source_label' => $data['source_label'] ?? 'مؤسستنا',
            'target_label' => $data['target_label'],
            'body' => $data['body'],
            'registry_number' => $data['registry_number'],
            'book_number' => $data['book_number'],
            'qr_payload' => 'CND-BOOK:' . $data['book_number'],
            'created_at' => $event->created_at,
            'updated_at' => $event->created_at,
        ]);

        $this->renderInternalLetter($document, $signer);

        return $document->fresh();
    }

    private function renderInternalLetter(FormalCorrespondenceDocument $document, User $signer): void
    {
        $template = config('document_templates.formal_correspondences.stage_internal_letter');
        if (! $template || ! is_file($template)) {
            return;
        }

        $relativePath = 'formal-correspondences/generated/demo/document-' . $document->id . '-' . uniqid() . '.docx';
        $absolutePath = Storage::disk('public')->path($relativePath);
        $qrPath = storage_path('app/private/tmp/seed-qr-' . uniqid() . '.png');

        if (! is_dir(dirname($qrPath))) {
            mkdir(dirname($qrPath), 0775, true);
        }

        app(SimpleQrImage::class)->make($document->qr_payload ?: $document->book_number, $qrPath);
        app(DocxTemplateRenderer::class)->render($template, $absolutePath, [
            'registry_number' => $document->registry_number ?: '',
            'book_number' => $document->book_number ?: '',
            'qr_payload' => $document->qr_payload ?: '',
            'gregorian_date' => now()->format('Y/m/d'),
            'hijri_date' => $this->hijriDate(),
            'recipient' => $document->target_label ?: '',
            'subject' => '',
            'title' => $document->title ?: '',
            'body' => $document->body ?: '',
            'source' => $document->source_label ?: '',
            'target' => $document->target_label ?: '',
            'signer_name' => $signer->name,
            'signer_role' => $signer->job_title ?: $this->roleLabel($signer->role),
        ], [
            'qr_code' => $qrPath,
        ]);

        @unlink($qrPath);
        $this->convertDocxToPdf($absolutePath);

        $document->update([
            'attachment_path' => $relativePath,
            'attachment_name' => $this->safeFileName($document->title ?: 'formal-letter') . '.docx',
            'attachment_mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'attachment_size' => Storage::disk('public')->size($relativePath),
        ]);
    }

    private function convertDocxToPdf(string $absolutePath): void
    {
        $binary = config('document_templates.libreoffice_binary');
        if (! $binary || ! is_file($binary)) {
            return;
        }

        $process = new Process([
            $binary,
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            dirname($absolutePath),
            $absolutePath,
        ]);
        $process->setTimeout(30);
        $process->run();
    }

    private function samplePdf(string $title): string
    {
        $safeTitle = preg_replace('/[^\x20-\x7E]/', '?', $title);
        $text = 'BT /F1 16 Tf 72 760 Td (CND demo external PDF) Tj 0 -28 Td (' . addcslashes($safeTitle, '\\()') . ') Tj ET';
        $objects = [
            '1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj',
            '2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj',
            '3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj',
            '4 0 obj<</Length ' . strlen($text) . ">>stream\n" . $text . "\nendstream endobj",
            '5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = ['0000000000 65535 f '];
        foreach ($objects as $object) {
            $offsets[] = str_pad((string) strlen($pdf), 10, '0', STR_PAD_LEFT) . ' 00000 n ';
            $pdf .= $object . "\n";
        }

        $xrefOffset = strlen($pdf);

        return $pdf
            . "xref\n0 " . (count($objects) + 1) . "\n"
            . implode("\n", $offsets) . "\n"
            . "trailer<</Size " . (count($objects) + 1) . "/Root 1 0 R>>\n"
            . "startxref\n{$xrefOffset}\n%%EOF\n";
    }

    private function hijriDate(): string
    {
        $formatter = new \IntlDateFormatter(
            'ar_SY@calendar=islamic-umalqura;numbers=latn',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::NONE,
            config('app.timezone'),
            \IntlDateFormatter::TRADITIONAL,
            'yyyy/MM/dd',
        );

        return $formatter->format(now()->toDateTime()) ?: now()->format('Y/m/d');
    }

    private function roleLabel(?string $role): string
    {
        return [
            'database_manager' => 'مدير قواعد البيانات',
            'general_manager' => 'المدير العام',
            'branch_manager' => 'مدير فرع',
            'department_head' => 'رئيس قسم',
            'office_manager' => 'رئيس مكتب',
            'technician' => 'فني',
            'employee' => 'موظف',
        ][$role] ?? 'مستخدم';
    }

    private function safeFileName(string $value): string
    {
        $slug = preg_replace('/[^\pL\pN\-_.]+/u', '-', trim($value));
        return trim($slug ?: 'document', '-');
    }
}
