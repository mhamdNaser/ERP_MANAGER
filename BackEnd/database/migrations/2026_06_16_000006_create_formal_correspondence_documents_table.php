<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formal_correspondence_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formal_correspondence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('formal_correspondence_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type')->default('uploaded');
            $table->string('title');
            $table->string('source_label')->nullable();
            $table->string('target_label')->nullable();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formal_correspondence_documents');
    }
};
