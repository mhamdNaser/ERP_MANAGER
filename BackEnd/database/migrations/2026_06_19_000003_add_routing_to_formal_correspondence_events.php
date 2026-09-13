<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formal_correspondence_events', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('actor_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->string('source_label')->nullable()->after('source_id');
            $table->string('target_type')->nullable()->after('source_label');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            $table->string('target_label')->nullable()->after('target_id');
            $table->string('action_required')->nullable()->after('target_label');
            $table->string('decision_type')->nullable()->after('action_required');
            $table->string('decision_status')->nullable()->after('decision_type');
            $table->timestamp('responded_at')->nullable()->after('decision_status');
        });

        Schema::create('formal_correspondence_stage_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formal_correspondence_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('response_type')->default('study');
            $table->text('body');
            $table->text('recommendation')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formal_correspondence_stage_responses');

        Schema::table('formal_correspondence_events', function (Blueprint $table) {
            $table->dropColumn([
                'source_type',
                'source_id',
                'source_label',
                'target_type',
                'target_id',
                'target_label',
                'action_required',
                'decision_type',
                'decision_status',
                'responded_at',
            ]);
        });
    }
};
