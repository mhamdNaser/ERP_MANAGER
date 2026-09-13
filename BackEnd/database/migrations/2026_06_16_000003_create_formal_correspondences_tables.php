<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_entities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        Schema::create('formal_correspondences', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->string('direction')->index();
            $table->string('status')->default('draft')->index();
            $table->string('subject');
            $table->text('summary')->nullable();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sender_external_entity_id')->nullable()->constrained('external_entities')->nullOnDelete();
            $table->foreignId('recipient_external_entity_id')->nullable()->constrained('external_entities')->nullOnDelete();
            $table->foreignId('recipient_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('recipient_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('word_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('qr_payload')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('formal_correspondence_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formal_correspondence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formal_correspondence_events');
        Schema::dropIfExists('formal_correspondences');
        Schema::dropIfExists('external_entities');
    }
};
