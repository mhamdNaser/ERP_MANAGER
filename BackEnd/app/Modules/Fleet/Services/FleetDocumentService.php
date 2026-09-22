<?php

namespace App\Modules\Fleet\Services;

use App\Models\FleetMission;
use App\Models\User;
use App\Modules\Communications\Services\DocxPdfConverter;
use App\Modules\Communications\Services\SignatureImage;
use App\Modules\Fleet\Repositories\Interfaces\FleetRepositoryInterface;
use App\Services\Documents\DocxTemplateRenderer;
use App\Services\Documents\SimpleQrImage;
use Illuminate\Support\Facades\Storage;

/**
 * يولّد وثيقة المهمة المعتمدة (Word + PDF) من قالب المؤسسة،
 * موقّعةً بتوقيع المدير العام الذي اعتمدها، مثل كتب المراسلات.
 * ريثما يصدر قالب خاص بالآليات يُستعمل قالب طلبات الموارد البشرية.
 */
class FleetDocumentService
{
    public function __construct(
        private DocxPdfConverter $converter,
        private SignatureImage $signature,
        private FleetRepositoryInterface $fleet,
    ) {}

    public function generate(FleetMission $mission, ?User $signer): void
    {
        $template = $this->template();
        if (! $template) {
            return;
        }

        $this->fleet->setDocumentFields($mission, ['document_number' => $mission->document_number ?: $this->nextNumber()]);
        $this->fleet->setDocumentFields($mission, ['qr_payload' => 'CND-FLEET:' . $mission->document_number]);

        $relativePath = 'fleet-missions/generated/' . now()->format('Y/m/d')
            . '/mission-' . $mission->id . '-' . uniqid() . '.docx';
        $absolutePath = Storage::disk('public')->path($relativePath);
        $temporaryQr = storage_path('app/private/tmp/fleet-qr-' . uniqid() . '.png');
        if (! is_dir(dirname($temporaryQr))) {
            mkdir(dirname($temporaryQr), 0775, true);
        }

        app(SimpleQrImage::class)->make($mission->qr_payload, $temporaryQr);

        // التوقيع يُدرج فقط إن كان للمعتمِد صورة صالحة، وإلا يُفرَّغ مكانه.
        $signaturePath = $this->signature->pngFor($signer);
        $images = ['qr_code' => $temporaryQr];
        $values = $this->values($mission, $signer);

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

        $mission->forceFill([
            'word_path' => $relativePath,
            'pdf_path' => $pdfAbsolute ? $this->converter->pdfPathFor($relativePath) : null,
        ])->save();
    }

    /** قالب الآليات إن وُجد، وإلا قالب طلبات الموارد البشرية. */
    private function template(): ?string
    {
        foreach ([config('document_templates.fleet.mission_approval'), config('document_templates.hr.request_approval')] as $path) {
            if ($path && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function values(FleetMission $mission, ?User $signer): array
    {
        $employee = $mission->user;

        return [
            'registry_number' => $mission->document_number ?: '',
            'qr_payload' => $mission->qr_payload ?: '',
            'gregorian_date' => now()->format('Y/m/d'),
            'hijri_date' => $this->hijriDate(),
            'recipient' => 'إلى السيد ' . ($employee?->name ?: ''),
            'subject' => 'مهمة عمل',
            'body' => $this->body($mission, $employee),
            'status' => 'موافقة نهائية بتاريخ ' . ($mission->decided_at?->format('Y/m/d') ?: now()->format('Y/m/d')),
            'signer_name' => $signer?->name ?: '',
            'signer_role' => $signer?->job_title ?: 'المدير العام',
        ];
    }

    private function body(FleetMission $mission, ?User $employee): string
    {
        $lines = [
            'الموظف: ' . ($employee?->name ?: '—') . ($employee?->job_title ? ' — ' . $employee->job_title : ''),
            'رقم المهمة: ' . $mission->reference_code,
            'نوع الطلب: مهمة عمل',
        ];

        if ($mission->start_date) {
            $lines[] = 'من تاريخ ' . $mission->start_date->format('Y/m/d')
                . ' إلى تاريخ ' . ($mission->end_date?->format('Y/m/d') ?: $mission->start_date->format('Y/m/d'))
                . ($mission->days ? ' (' . rtrim(rtrim(number_format($mission->days, 1), '0'), '.') . ' يوم)' : '');
        }

        if ($mission->destination) {
            $lines[] = 'الجهة المقصودة: ' . $mission->destination;
        }

        $lines[] = '';
        $lines[] = 'السبب: ' . $mission->reason;
        $lines[] = '';
        $lines[] = 'وقد جرى اعتماد المهمة أصولاً ضمن مسار الموافقات المعتمد في الإدارة.';

        return implode("\n", $lines);
    }

    private function nextNumber(): string
    {
        $sequence = $this->fleet->nextDocumentSequence();

        return 'FL-DOC-' . now()->format('Ymd') . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
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
