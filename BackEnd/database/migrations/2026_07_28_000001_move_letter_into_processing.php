<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المعالجة صارت هي كتاب الإحالة نفسه: حقول الكتاب وملفاته تنتقل إلى المعالجة،
 * ولا يبقى في جدول الوثائق سوى المرفقات المرفوعة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formal_correspondence_events', function (Blueprint $table) {
            $table->string('registry_number')->nullable()->after('target_label');
            $table->string('letter_title')->nullable()->after('registry_number');
            $table->text('letter_body')->nullable()->after('letter_title');
            $table->string('book_number')->nullable()->after('letter_body');
            $table->string('qr_payload')->nullable()->after('book_number');
            $table->string('word_path')->nullable()->after('qr_payload');
            $table->string('pdf_path')->nullable()->after('word_path');
        });

        $this->moveLettersIntoEvents();
    }

    public function down(): void
    {
        Schema::table('formal_correspondence_events', function (Blueprint $table) {
            $table->dropColumn([
                'registry_number', 'letter_title', 'letter_body',
                'book_number', 'qr_payload', 'word_path', 'pdf_path',
            ]);
        });
    }

    private function moveLettersIntoEvents(): void
    {
        $letters = DB::table('formal_correspondence_documents')
            ->where('document_type', 'internal_letter')
            ->whereNotNull('formal_correspondence_event_id')
            ->orderBy('id')
            ->get();

        foreach ($letters as $letter) {
            $wordPath = $letter->attachment_path;
            $pdfPath = $wordPath && str_ends_with(strtolower($wordPath), '.docx')
                ? preg_replace('/\.docx$/i', '.pdf', $wordPath)
                : null;

            DB::table('formal_correspondence_events')
                ->where('id', $letter->formal_correspondence_event_id)
                ->update([
                    'registry_number' => $letter->registry_number,
                    'letter_title' => $letter->title,
                    'letter_body' => $letter->body,
                    'book_number' => $letter->book_number,
                    'qr_payload' => $letter->qr_payload,
                    'word_path' => $wordPath,
                    'pdf_path' => $pdfPath,
                ]);
        }

        // الكتب لم تعد كياناً مستقلاً؛ ملفاتها صارت مملوكة للمعالجة.
        DB::table('formal_correspondence_documents')->where('document_type', 'internal_letter')->delete();
    }
};
