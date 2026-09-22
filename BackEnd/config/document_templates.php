<?php

return [
    'libreoffice_binary' => env('LIBREOFFICE_BINARY', '/snap/bin/libreoffice'),
    'local_word_pdf_fallback' => env('LOCAL_WORD_PDF_FALLBACK', true),

    // النسخ السابقة من القوالب المستبدَلة — مجلد قابل للضبط كي تعمل الاختبارات
    // على مجلد مؤقت بدل الكتابة فوق قوالب المؤسسة.
    'backups_path' => env('DOCUMENT_TEMPLATE_BACKUPS', resource_path('templates/backups')),

    'qr' => [
        'logo_path' => env('QR_LOGO_PATH', base_path('../FrontEnd/src/assets/logo.jpg')),
        'brand_text' => env('QR_BRAND_TEXT', 'CND'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Editable document templates
    |--------------------------------------------------------------------------
    |
    | These files are the official Word templates that can later be populated
    | from form data and converted to downloadable DOCX/PDF documents.
    |
    */

    'formal_correspondences' => [
        'stage_internal_letter' => resource_path('templates/documents/formal-correspondences/stage-internal-letter.docx'),
        'stage_external_reply' => resource_path('templates/documents/formal-correspondences/stage-external-reply.docx'),
        'stage_study_report' => resource_path('templates/documents/formal-correspondences/stage-study-report.docx'),
        'stage_execution_report' => resource_path('templates/documents/formal-correspondences/stage-execution-report.docx'),
    ],

    'hr' => [
        'request_approval' => resource_path('templates/documents/hr/request-approval.docx'),

        // النموذجان الرسميان للإجازات. الأصل الورقي الفارغ بجانبهما في blank/.
        'leave_request' => resource_path('templates/documents/hr/leave-request.docx'),
        'hourly_leave_request' => resource_path('templates/documents/hr/hourly-leave-request.docx'),
    ],

    'fleet' => [
        'mission_approval' => resource_path('templates/documents/fleet/mission-approval.docx'),
    ],

    'messages' => [
        'message_export' => resource_path('templates/documents/messages/message-export.docx'),
    ],

    'reports' => [
        'approved_report' => resource_path('templates/documents/reports/approved-report.docx'),
    ],

    'custom_forms' => [
        'submission_export' => resource_path('templates/documents/custom-forms/submission-export.docx'),
    ],
];
