<?php

use App\Services\Documents\DocxTemplateRenderer;

/** النص المقروء من مستند Word المولَّد. */
function renderedText(string $path): string
{
    $zip = new ZipArchive();
    expect($zip->open($path))->toBeTrue();

    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    return preg_replace('/<[^>]+>/', '', $xml);
}

/** مسار قالب داخل BackEnd/resources — الاختبارات الوحدوية لا تُقلع التطبيق. */
function hrTemplatePath(string $relative): string
{
    return dirname(__DIR__, 2) . '/resources/templates/documents/hr/' . $relative;
}

function renderHrForm(string $relative, array $values): string
{
    $template = hrTemplatePath($relative);
    expect(is_file($template))->toBeTrue();

    $target = sys_get_temp_dir() . '/cnd-hr-form-' . uniqid() . '.docx';
    (new DocxTemplateRenderer())->render($template, $target, $values);

    $text = renderedText($target);
    @unlink($target);

    return $text;
}

it('fills the administrative leave form leaving no placeholder behind', function () {
    $text = renderHrForm('leave-request.docx', [
        'registry_number' => 'HR-DOC-20260922-0007',
        'gregorian_date' => '2026/09/22',
        'hijri_date' => '1448/03/29',
        'employee_name' => 'سامر الخطيب',
        'job_title' => 'رئيس فرع الآليات',
        'employee_number' => 'CND-0421',
        'days' => '3',
        'reason' => 'ظرف عائلي طارئ.',
        'start_date' => '2026/10/01',
        'end_date' => '2026/10/03',
    ]);

    expect($text)
        ->not->toContain('{{')
        ->toContain('سامر الخطيب')
        ->toContain('رئيس فرع الآليات')
        ->toContain('CND-0421')
        ->toContain('ظرف عائلي طارئ.')
        ->toContain('2026/10/01')
        ->toContain('2026/10/03')
        ->toContain('HR-DOC-20260922-0007');
});

it('fills the hourly leave form leaving no placeholder behind', function () {
    $text = renderHrForm('hourly-leave-request.docx', [
        'registry_number' => 'HR-DOC-20260922-0008',
        'gregorian_date' => '2026/09/22',
        'hijri_date' => '1448/03/29',
        'employee_name' => 'وائل منصور',
        'job_title' => 'موظف آليات',
        'employee_number' => 'CND-0422',
        'start_time' => '10:00',
        'end_time' => '13:00',
        'date' => '2026/09/30',
    ]);

    expect($text)
        ->not->toContain('{{')
        ->toContain('وائل منصور')
        ->toContain('موظف آليات')
        ->toContain('CND-0422')
        ->toContain('10:00')
        ->toContain('13:00')
        ->toContain('2026/09/30');
});

/** النسخة الفارغة تبقى ورقةً تُملأ بالقلم: لا حقول فيها ولا أثر لها. */
it('keeps the blank forms free of any placeholder', function () {
    foreach (['leave-request.docx', 'hourly-leave-request.docx'] as $name) {
        $path = hrTemplatePath('blank/' . $name);
        expect(is_file($path))->toBeTrue();
        expect(renderedText($path))->not->toContain('{{');
    }
});
