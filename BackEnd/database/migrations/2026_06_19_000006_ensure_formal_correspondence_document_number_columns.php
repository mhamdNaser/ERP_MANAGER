<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('formal_correspondence_documents', 'registry_number')) {
            Schema::table('formal_correspondence_documents', function (Blueprint $table) {
                $table->string('registry_number')->nullable();
            });
        }

        if (! Schema::hasColumn('formal_correspondence_documents', 'book_number')) {
            Schema::table('formal_correspondence_documents', function (Blueprint $table) {
                $table->string('book_number')->nullable()->unique();
            });
        }

        if (! Schema::hasColumn('formal_correspondence_documents', 'qr_payload')) {
            Schema::table('formal_correspondence_documents', function (Blueprint $table) {
                $table->string('qr_payload')->nullable();
            });
        }
    }

    public function down(): void
    {
        $columns = collect(['registry_number', 'book_number', 'qr_payload'])
            ->filter(fn (string $column) => Schema::hasColumn('formal_correspondence_documents', $column))
            ->all();

        if ($columns) {
            Schema::table('formal_correspondence_documents', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
