<?php

namespace App\Modules\Communications\Services;

use App\Models\FormalCorrespondenceEvent;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * تحويل كتاب المعالجة من DOCX إلى PDF: LibreOffice على الخادم، وWord/Edge محلياً على Windows.
 */
class DocxPdfConverter
{
    public function convert(string $absolutePath, ?FormalCorrespondenceEvent $event = null): ?string
    {
        $pdfPath = $this->pdfPathFor($absolutePath);
        $binary = config('document_templates.libreoffice_binary');

        if ($binary && is_file($binary)) {
            // LibreOffice يحتاج ملف تعريف قابلاً للكتابة؛ بيت www-data ليس كذلك فيفشل بالرمز 77.
            $profile = storage_path('app/private/libreoffice');
            if (! is_dir($profile)) {
                mkdir($profile, 0775, true);
            }

            $process = new Process([
                $binary,
                '--headless',
                '--norestore',
                '-env:UserInstallation=file://' . $profile,
                '--convert-to', 'pdf',
                '--outdir', dirname($absolutePath),
                $absolutePath,
            ], null, ['HOME' => $profile]);
            $process->setTimeout(60);
            $process->run();

            if ($process->isSuccessful() && is_file($pdfPath)) {
                return $pdfPath;
            }

            logger()->warning('LibreOffice PDF conversion failed.', [
                'exit_code' => $process->getExitCode(),
                'error' => trim($process->getErrorOutput()),
            ]);
        }

        if (app()->environment('local')
            && PHP_OS_FAMILY === 'Windows'
            && config('document_templates.local_word_pdf_fallback', true)) {
            if ($this->convertWithMicrosoftWord($absolutePath, $pdfPath)) {
                return $pdfPath;
            }

            if ($event && $this->createLocalPreview($event, $pdfPath)) {
                return $pdfPath;
            }
        }

        return is_file($pdfPath) ? $pdfPath : null;
    }

    public function pdfPathFor(string $docxPath): string
    {
        return preg_replace('/\.docx$/i', '.pdf', $docxPath) ?: $docxPath . '.pdf';
    }

    private function convertWithMicrosoftWord(string $absolutePath, string $pdfPath): bool
    {
        $script = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
$word = $null
$document = $null
try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    $document = $word.Documents.Open($env:CND_DOCX_SOURCE, $false, $true)
    $document.SaveAs2($env:CND_PDF_TARGET, 17)
} finally {
    if ($document) { $document.Close($false) }
    if ($word) { $word.Quit() }
    if ($document) { [void][Runtime.InteropServices.Marshal]::ReleaseComObject($document) }
    if ($word) { [void][Runtime.InteropServices.Marshal]::ReleaseComObject($word) }
}
POWERSHELL;

        try {
            $process = new Process(
                ['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', $script],
                null,
                ['CND_DOCX_SOURCE' => $absolutePath, 'CND_PDF_TARGET' => $pdfPath],
            );
            $process->setTimeout(45);
            $process->run();

            return $process->isSuccessful() && is_file($pdfPath);
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /** معاينة محلية بديلة عبر Edge عندما لا يتوفر Word ولا LibreOffice. */
    private function createLocalPreview(FormalCorrespondenceEvent $event, string $pdfPath): bool
    {
        $edge = collect([
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        ])->first(fn (string $path) => is_file($path));

        if (! $edge) return false;

        $temporaryHtml = storage_path('app/private/tmp/letter-preview-' . uniqid() . '.html');
        $profile = storage_path('app/private/tmp/edge-' . uniqid());
        if (! is_dir(dirname($temporaryHtml))) {
            mkdir(dirname($temporaryHtml), 0775, true);
        }

        file_put_contents($temporaryHtml, $this->previewHtml($event));

        try {
            $uri = 'file:///' . str_replace(['\\', ' '], ['/', '%20'], $temporaryHtml);
            $process = new Process([
                $edge, '--headless', '--disable-gpu', '--disable-software-rasterizer', '--no-sandbox',
                '--no-pdf-header-footer', '--user-data-dir=' . $profile, '--print-to-pdf=' . $pdfPath, $uri,
            ]);
            $process->setTimeout(30);
            $process->run();

            return $process->isSuccessful() && is_file($pdfPath);
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        } finally {
            @unlink($temporaryHtml);
            File::deleteDirectory($profile);
        }
    }

    private function previewHtml(FormalCorrespondenceEvent $event): string
    {
        $escape = fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subject = $escape($event->formalCorrespondence()->value('subject'));

        return '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><style>'
            . '@page{size:A4;margin:18mm}body{font-family:Tahoma,Arial,sans-serif;color:#17211f;line-height:1.9;font-size:14px}'
            . '.meta{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;border-bottom:1px solid #ccd7d4;padding-bottom:14px;margin-bottom:24px}'
            . '.label{color:#687a76;font-size:11px}.value{font-weight:700}h1{font-size:20px;text-align:center;margin:20px 0}'
            . '.footer{margin-top:42px;border-top:1px solid #ccd7d4;padding-top:14px}</style></head><body>'
            . '<div class="meta"><div><div class="label">رقم الديوان</div><div class="value">' . $escape($event->registry_number) . '</div></div>'
            . '<div><div class="label">رقم الكتاب</div><div class="value">' . $escape($event->book_number) . '</div></div>'
            . '<div><div class="label">الجهة المصدرة</div><div class="value">' . $escape($event->source_label) . '</div></div>'
            . '<div><div class="label">الجهة المخاطبة</div><div class="value">' . $escape($event->target_label) . '</div></div></div>'
            . '<h1>' . $escape($event->letter_title) . '</h1>'
            . ($subject !== '' ? '<p><strong>الموضوع: </strong>' . $subject . '</p>' : '')
            . '<article>' . nl2br($escape($event->letter_body), false) . '</article>'
            . '<div class="footer"><span class="label">رمز التحقق الداخلي: </span>' . $escape($event->qr_payload) . '</div>'
            . '</body></html>';
    }
}
