<?php

use App\Models\FormalCorrespondenceDocument;
use Illuminate\Support\Facades\Storage;

it('appends cache busting version query to generated document urls', function () {
    Storage::fake('public');

    $document = new FormalCorrespondenceDocument([
        'document_type' => 'internal_letter',
        'title' => 'كتاب اختبار',
        'source_label' => 'المصدر',
        'target_label' => 'الهدف',
        'body' => 'نص الاختبار',
        'attachment_path' => 'formal-correspondences/generated/test.docx',
        'attachment_name' => 'test.docx',
        'attachment_mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'attachment_size' => 123,
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    Storage::disk('public')->put('formal-correspondences/generated/test.docx', 'docx');
    Storage::disk('public')->put('formal-correspondences/generated/test.pdf', 'pdf');

    expect($document->attachment_url)
        ->toContain('formal-correspondences/generated/test.docx')
        ->and($document->attachment_url)
        ->toContain('?v=')
        ->and($document->pdf_url)
        ->toContain('?v=');
});
