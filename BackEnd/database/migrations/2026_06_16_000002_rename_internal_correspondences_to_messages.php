<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('correspondence_replies') && ! Schema::hasTable('message_replies')) {
            Schema::rename('correspondence_replies', 'message_replies');
        }

        if (Schema::hasTable('correspondences') && ! Schema::hasTable('messages')) {
            Schema::rename('correspondences', 'messages');
        }

        if (Schema::hasColumn('message_replies', 'correspondence_id') && ! Schema::hasColumn('message_replies', 'message_id')) {
            Schema::table('message_replies', fn (Blueprint $table) => $table->renameColumn('correspondence_id', 'message_id'));
        }

        DB::table('permissions')->where('name', 'correspondences.view')->update(['name' => 'messages.view']);
        DB::table('permissions')->where('name', 'correspondences.create')->update(['name' => 'messages.create']);
        DB::table('permissions')->where('name', 'correspondences.reply')->update(['name' => 'messages.reply']);
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'messages.view')->update(['name' => 'correspondences.view']);
        DB::table('permissions')->where('name', 'messages.create')->update(['name' => 'correspondences.create']);
        DB::table('permissions')->where('name', 'messages.reply')->update(['name' => 'correspondences.reply']);

        if (Schema::hasColumn('message_replies', 'message_id') && ! Schema::hasColumn('message_replies', 'correspondence_id')) {
            Schema::table('message_replies', fn (Blueprint $table) => $table->renameColumn('message_id', 'correspondence_id'));
        }

        if (Schema::hasTable('messages') && ! Schema::hasTable('correspondences')) {
            Schema::rename('messages', 'correspondences');
        }

        if (Schema::hasTable('message_replies') && ! Schema::hasTable('correspondence_replies')) {
            Schema::rename('message_replies', 'correspondence_replies');
        }
    }
};
