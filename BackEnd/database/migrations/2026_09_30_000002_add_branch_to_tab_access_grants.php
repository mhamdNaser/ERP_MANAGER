<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جمهور التبويب يشمل الفرع الكامل و«الجميع» أيضاً. الصف يحمل واحداً فقط:
 * الجميع، أو فرعاً، أو قسماً، أو شخصاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tab_access_grants', function (Blueprint $table) {
            $table->boolean('everyone')->default(false)->after('tab');
            $table->foreignId('branch_id')->nullable()->after('everyone')->constrained('branches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tab_access_grants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('everyone');
        });
    }
};
