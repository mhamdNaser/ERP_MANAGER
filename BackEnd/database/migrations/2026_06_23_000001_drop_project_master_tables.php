<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('project_databases');
        Schema::dropIfExists('project_master_settings');
    }

    public function down(): void
    {
        //
    }
};
