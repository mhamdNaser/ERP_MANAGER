<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cnd_notifications', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->after('custom_form_publication_id')->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cnd_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_id');
        });
    }
};
