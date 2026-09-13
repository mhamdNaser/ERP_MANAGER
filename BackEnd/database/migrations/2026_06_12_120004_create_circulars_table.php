<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('circulars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issuer_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->string('audience');
            $table->timestamps();
        });
        Schema::create('circular_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circular_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->unique(['circular_id', 'user_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('circular_recipients');
        Schema::dropIfExists('circulars');
    }
};
