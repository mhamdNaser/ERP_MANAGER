<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cnd_notifications', function (Blueprint $table) {
            $table->foreignId('custom_form_id')->nullable()->after('circular_id')->constrained('custom_forms')->nullOnDelete();
            $table->foreignId('custom_form_publication_id')->nullable()->after('custom_form_id')->constrained('custom_form_publications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cnd_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('custom_form_publication_id');
            $table->dropConstrainedForeignId('custom_form_id');
        });
    }
};
