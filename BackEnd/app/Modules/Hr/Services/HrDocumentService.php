<?php

namespace App\Modules\Hr\Services;

use App\Models\HrRequest;
use App\Models\User;
use App\Modules\Communications\Services\DocxPdfConverter;
use App\Modules\Communications\Services\SignatureImage;
use App\Modules\Hr\Repositories\Interfaces\HrRepositoryInterface;
use App\Services\Documents\DocxTemplateRenderer;
use App\Services\Documents\SimpleQrImage;
use Illuminate\Support\Facades\Storage;

/**
 * يولّد وثيقة الطلب المعتمد (Word + PDF) من قالب المؤسسة،
 * موقّعةً بتوقيع المدير العام الذي اعتمدها، مثل كتب المراسلات.
 */
class HrDocumentService
{
    public function __construct(
        private DocxPdfConverter $converter,
        private SignatureImage $signature,
        private HrRepositoryInterface $hr,
    ) {}

    public function generate(HrRequest $request, ?User $signer): void
    {
        $template = config('document_templates.hr.request_approval');
        if (! $template || ! is_file($template)) {
            return;
        }

        $this->hr->setDocumentFields($request, ['document_number' => $request->document_number ?: $this->nextNumber()]);
        $this->hr->setDocumentFields($request, ['qr_payload' => 'CND-HR:' . $request->document_number]);

        $relativePath = 'hr-requests/generated/' . now()->format('Y/m/d')
            . '/request-' . $request->id . '-' . uniqid() . '.docx';
        $absolutePath = Storage::disk('public')->path($relativePath);
        $temporaryQr = storage_path('app/private/tmp/hr-qr-' . uniqid() . '.png');
        if (! is_dir(dirname($temporaryQr))) {
            mkdir(dirname($temporaryQr), 0775, true);
        }

        app(SimpleQrImage::class)->make($request->qr_payload, $temporaryQr);

        // التوقيع يُدرج فقط إن كان للمعتمِد صورة صالحة، وإلا يُفرَّغ مكانه.
        $signaturePath = $this->signature->pngFor($signer);
        $images = ['qr_code' => $temporaryQr];
        $values = $this->values($request, $signer);

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

        $pdfAbsolute = $this->converter->convert($absolutePath);

        $request->forceFill([
            'word_path' => $relativePath,
            'pdf_path' => $pdfAbsolute ? $this->converter->pdfPathFor($relativePath) : null,
        ])->save();
    }

    private function values(HrRequest $request, ?User $signer): array
    {
        $employee = $request->user;

        return [
            'registry_number' => $request->document_number ?: '',
            'qr_payload' => $request->qr_payload ?: '',
            'gregorian_date' => now()->format('Y/m/d'),
            'hijri_date' => $this->hijriDate(),
            'recipient' => 'إلى السيد ' . ($employee?->name ?: ''),
            'subject' => $this->typeLabel($request),
            'body' => $this->body($request, $employee),
            'status' => 'موافقة نهائية بتاريخ ' . ($request->decided_at?->format('Y/m/d') ?: now()->format('Y/m/d')),
            'signer_name' => $signer?->name ?: '',
            'signer_role' => $signer?->job_title ?: 'المدير العام',
        ];
    }

    private function body(HrRequest $request, ?User $employee): string
    {
        $lines = [
            'الموظف: ' . ($employee?->name ?: '—') . ($employee?->job_title ? ' — ' . $employee->job_title : ''),
            'رقم الطلب: ' . $request->reference_code,
            'نوع الطلب: ' . $this->typeLabel($request),
        ];

        if ($request->start_date) {
            $lines[] = 'من تاريخ ' . $request->start_date->format('Y/m/d')
                . ' إلى تاريخ ' . ($request->end_date?->format('Y/m/d') ?: $request->start_date->format('Y/m/d'))
                . ($request->days ? ' (' . rtrim(rtrim(number_format($request->days, 1), '0'), '.') . ' يوم)' : '');
        }

        if ($request->start_time) {
            $lines[] = 'من الساعة ' . substr($request->start_time, 0, 5) . ' إلى الساعة ' . substr((string) $request->end_time, 0, 5);
        }

        if ($request->destination) {
            $lines[] = 'الجهة المقصودة: ' . $request->destination;
        }

        $lines[] = '';
        $lines[] = 'السبب: ' . $request->reason;
        $lines[] = '';
        $lines[] = 'وقد جرى اعتماد الطلب أصولاً ضمن مسار الموافقات المعتمد في الإدارة.';

        return implode("\n", $lines);
    }

    private function typeLabel(HrRequest $request): string
    {
        $type = [
            'leave' => 'إجازة',
            'departure' => 'مغادرة ساعية',
            'mission' => 'مهمة عمل',
            'document' => 'طلب وثيقة',
        ][$request->type] ?? $request->type;

        $subtype = [
            'annual' => 'سنوية',
            'sick' => 'مرضية',
            'unpaid' => 'بلا راتب',
            'employment' => 'شهادة تعريف',
            'salary' => 'شهادة راتب',
        ][$request->subtype] ?? null;

        return $subtype ? $type . ' ' . $subtype : $type;
    }

    private function nextNumber(): string
    {
        $sequence = $this->hr->nextDocumentSequence();

        return 'HR-DOC-' . now()->format('Ymd') . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
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
}
