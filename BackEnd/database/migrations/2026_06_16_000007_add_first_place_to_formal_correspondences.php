<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formal_correspondences', function (Blueprint $table) {
            $table->string('first_place')->nullable()->after('issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('formal_correspondences', function (Blueprint $table) {
            $table->dropColumn('first_place');
        });
    }
};
