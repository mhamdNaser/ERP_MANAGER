<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formal_correspondence_documents', function (Blueprint $table) {
            $table->string('book_number')->nullable()->unique()->after('body');
            $table->string('qr_payload')->nullable()->after('book_number');
        });
    }

    public function down(): void
    {
        Schema::table('formal_correspondence_documents', function (Blueprint $table) {
            $table->dropColumn(['book_number', 'qr_payload']);
        });
    }
};
