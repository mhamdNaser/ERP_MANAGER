<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * يسمح بوجود قسم "تابع للإدارة مباشرة" بلا فرع (مثل قسم الدراسات)، ويحوّل
 * حذف الفرع إلى تفريغ branch_id بدل حذف القسم بالسلسلة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function ($table) {
            $table->dropForeign(['branch_id']);
        });
        Schema::table('departments', function ($table) {
            $table->foreignId('branch_id')->nullable()->change();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function ($table) {
            $table->dropForeign(['branch_id']);
        });
        Schema::table('departments', function ($table) {
            $table->foreignId('branch_id')->nullable(false)->change();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });
    }
};
