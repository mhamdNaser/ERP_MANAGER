<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** الطلب المعتمد يُطبع كوثيقة رسمية موقّعة، مثل كتب المراسلات. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_requests', function (Blueprint $table) {
            $table->string('document_number')->nullable()->after('decided_at');
            $table->string('qr_payload')->nullable()->after('document_number');
            $table->string('word_path')->nullable()->after('qr_payload');
            $table->string('pdf_path')->nullable()->after('word_path');
        });
    }

    public function down(): void
    {
        Schema::table('hr_requests', function (Blueprint $table) {
            $table->dropColumn(['document_number', 'qr_payload', 'word_path', 'pdf_path']);
        });
    }
};
