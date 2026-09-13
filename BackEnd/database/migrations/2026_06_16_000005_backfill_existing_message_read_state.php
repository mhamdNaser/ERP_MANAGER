<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('messages')->whereNull('sender_read_at')->update(['sender_read_at' => DB::raw('created_at')]);
        DB::table('messages')->whereNull('recipient_read_at')->update(['recipient_read_at' => DB::raw('COALESCE(read_at, created_at)')]);
    }

    public function down(): void
    {
        DB::table('messages')->update(['sender_read_at' => null, 'recipient_read_at' => null]);
    }
};
