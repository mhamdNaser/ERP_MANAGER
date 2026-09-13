<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_replies', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('content');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime')->nullable()->after('attachment_name');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('last_reply_at')->nullable()->after('read_at');
            $table->foreignId('last_reply_sender_id')->nullable()->after('last_reply_at')->constrained('users')->nullOnDelete();
            $table->timestamp('sender_read_at')->nullable()->after('last_reply_sender_id');
            $table->timestamp('recipient_read_at')->nullable()->after('sender_read_at');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_reply_sender_id');
            $table->dropColumn(['last_reply_at', 'sender_read_at', 'recipient_read_at']);
        });

        Schema::table('message_replies', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size']);
        });
    }
};
