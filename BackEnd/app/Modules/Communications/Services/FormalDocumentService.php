<?php

namespace App\Modules\Communications\Services;

use App\Models\FormalCorrespondence;
use App\Models\FormalCorrespondenceEvent;
use App\Models\User;
use App\Modules\Communications\Repositories\Interfaces\FormalCorrespondenceRepositoryInterface;
use App\Services\Documents\DocxTemplateRenderer;
use App\Services\Documents\SimpleQrImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * المعالجة هي الكتاب: تولّد ملف Word وPDF من قالب المؤسسة بقيم المعالجة،
 * وتحفظ مرفقاتها المرفوعة كوثائق تابعة لها.
 */
class FormalDocumentService
{
    public function __construct(
        private FormalTimelineService $timeline,
        private DocxPdfConverter $converter,
        private SignatureImage $signature,
        private FormalCorrespondenceRepositoryInterface $formal,
    ) {}

    /** يملأ حقول الكتاب على المعالجة ثم يولّد ملفيها. */
    public function buildProcessingLetter(FormalCorrespondenceEvent $event, array $data, ?User $actor = null): void
    {
        // رقم الكتاب يُحسم أولاً لأن رمز QR مشتقٌّ منه.
        $bookNumber = $event->book_number ?: $this->nextBookNumber();

        $this->formal->updateEvent($event, [
            'registry_number' => $data['registry_number'] ?? $event->registry_number,
            'letter_title' => $data['document_title'] ?? $event->letter_title ?: 'كتاب صادر عن مؤسستنا',
            'letter_body' => $data['document_body'] ?? $event->letter_body,
            'book_number' => $bookNumber,
            'qr_payload' => 'CND-BOOK:' . $bookNumber,
        ]);

        $this->renderLetter($event, $this->timeline->signerForEvent($event, $actor));
    }

    public function storeEventAttachments(Request $request, FormalCorrespondence $item, FormalCorrespondenceEvent $event): void
    {
        $rows = collect($request->file('attachments', []))->map(fn ($file) => [
            'formal_correspondence_event_id' => $event->id,
            'document_type' => 'uploaded',
            'title' => $file->getClientOriginalName(),
            'source_label' => $event->source_label,
            'target_label' => $event->target_label,
            'attachment_path' => $file->store('formal-correspondences/documents', 'public'),
            'attachment_name' => $file->getClientOriginalName(),
            'attachment_mime' => $file->getClientMimeType(),
            'attachment_size' => $file->getSize(),
        ])->all();

        $this->formal->createDocuments($item, $rows);
    }

    public function nextBookNumber(): string
    {
        $sequence = $this->formal->nextBookSequence();

        return 'CND-BOOK-' . now()->format('Ymd') . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function renderLetter(FormalCorrespondenceEvent $event, ?User $signer = null): void
    {
        $template = config('document_templates.formal_correspondences.stage_internal_letter');
        if (! $template || ! is_file($template)) {
            return;
        }

        $relativePath = 'formal-correspondences/generated/' . now()->format('Y/m/d')
            . '/processing-' . $event->id . '-' . uniqid() . '.docx';
        $absolutePath = Storage::disk('public')->path($relativePath);
        $temporaryQr = storage_path('app/private/tmp/qr-' . uniqid() . '.png');
        if (! is_dir(dirname($temporaryQr))) {
            mkdir(dirname($temporaryQr), 0775, true);
        }

        app(SimpleQrImage::class)->make($event->qr_payload ?: $event->book_number ?: (string) $event->id, $temporaryQr);

        // التوقيع يُدرج فقط إذا كان للموقّع صورة صالحة؛ وإلا يُفرَّغ مكانه بدل رسم مربع.
        $signaturePath = $this->signature->pngFor($signer);
        $images = ['qr_code' => $temporaryQr];
        $values = $this->templateValues($event, $signer);

        if ($signaturePath) {
            $images['signature'] = [
                'path' => $signaturePath,
                'cx' => 2100000,
                'cy' => 720000,
                'name' => 'digital-signature.png',
            ];
        } else {
            $values['signature'] = '';
        }

        app(DocxTemplateRenderer::class)->render($template, $absolutePath, $values, $images);

        @unlink($temporaryQr);
        $this->signature->cleanup($signaturePath);

        $pdfAbsolute = $this->converter->convert($absolutePath, $event);

        $this->formal->updateEvent($event, [
            'word_path' => $relativePath,
            'pdf_path' => $pdfAbsolute ? $this->converter->pdfPathFor($relativePath) : null,
        ]);
    }

    private function templateValues(FormalCorrespondenceEvent $event, ?User $signer): array
    {
        return [
            'registry_number' => $event->registry_number ?: '',
            'book_number' => $event->book_number ?: '',
            'qr_payload' => $event->qr_payload ?: '',
            'gregorian_date' => now()->format('Y/m/d'),
            'hijri_date' => $this->hijriDate(),
            'recipient' => $event->target_label ?: '',
            'subject' => $event->formalCorrespondence()->value('subject') ?: '',
            'title' => $event->letter_title ?: '',
            'body' => $event->letter_body ?: '',
            'source' => $event->source_label ?: '',
            'target' => $event->target_label ?: '',
            'signer_name' => $signer?->name ?: '',
            'signer_role' => $signer?->job_title ?: $this->roleLabel($signer?->role),
        ];
    }

    public function hijriDate(): string
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
            'general_manager' => 'المدير العام',
            'branch_manager' => 'مدير فرع',
            'department_head' => 'رئيس قسم',
            'office_manager' => 'رئيس مكتب',
            'database_manager' => 'مدير قاعدة البيانات',
            'technician' => 'فني',
            'employee' => 'موظف',
        ][$role] ?? ($role ?: '');
    }
}
