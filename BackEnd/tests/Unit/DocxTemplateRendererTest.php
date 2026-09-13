<?php

use App\Services\Documents\DocxTemplateRenderer;
use App\Services\Documents\SimpleQrImage;

it('fills split Word placeholders from the formal correspondence template', function () {
    $basePath = dirname(__DIR__, 2);
    $template = $basePath . '/resources/templates/documents/formal-correspondences/stage-internal-letter.docx';
    $target = sys_get_temp_dir() . '/cnd-docx-renderer-test-' . uniqid() . '.docx';
    $qr = sys_get_temp_dir() . '/cnd-docx-renderer-qr-' . uniqid() . '.png';

    (new SimpleQrImage())->make('CND-BOOK-UNIT', $qr);
    (new DocxTemplateRenderer())->render($template, $target, [
        'registry_number' => '888',
        'book_number' => 'CND-BOOK-UNIT',
        'qr_payload' => 'CND-BOOK:CND-BOOK-UNIT',
        'gregorian_date' => '2026/06/19',
        'hijri_date' => '1448/01/04',
        'recipient' => 'إلى السيد مدير الاختبار',
        'subject' => '',
        'title' => '',
        'body' => 'نص اختبار تعبئة قالب وورد',
        'source' => 'الديوان',
        'target' => 'إلى السيد مدير الاختبار',
        'signer_name' => 'مستخدم اختبار',
        'signer_role' => 'صفة اختبار',
    ], [
        'qr_code' => $qr,
    ]);

    $zip = new ZipArchive();
    expect($zip->open($target))->toBeTrue();

    $documentXml = $zip->getFromName('word/document.xml');
    expect($documentXml)
        ->not->toContain('{{')
        ->toContain('888')
        ->toContain('مدير الاختبار')
        ->toContain('نص اختبار تعبئة قالب وورد')
        ->toContain('مستخدم اختبار');

    $hasGeneratedQr = false;
    for ($index = 0; $index < $zip->numFiles; $index += 1) {
        $name = $zip->getNameIndex($index);
        if (str_starts_with($name, 'word/media/generated-qr_code-')) {
            $hasGeneratedQr = true;
            break;
        }
    }

    $zip->close();
    @unlink($target);
    @unlink($qr);

    expect($hasGeneratedQr)->toBeTrue();
});
