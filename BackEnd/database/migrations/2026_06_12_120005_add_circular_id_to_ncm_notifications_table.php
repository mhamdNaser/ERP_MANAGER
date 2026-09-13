<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cnd_notifications', function (Blueprint $table) {
            $table->foreignId('circular_id')->nullable()->after('report_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cnd_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('circular_id');
        });
    }
};
