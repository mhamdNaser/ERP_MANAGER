<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('users', fn(Blueprint $table) => $table->foreignId('office_id')->nullable()->after('department_id')->constrained()->nullOnDelete());
    }
    public function down(): void
    {
        Schema::table('users', fn(Blueprint $table) => $table->dropConstrainedForeignId('office_id'));
        Schema::dropIfExists('offices');
    }
};
