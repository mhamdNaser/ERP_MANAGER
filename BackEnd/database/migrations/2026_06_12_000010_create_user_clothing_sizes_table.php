<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_clothing_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('shoe_size')->nullable();
            $table->string('trouser_size')->nullable();
            $table->string('shirt_size')->nullable();
            $table->string('jacket_size')->nullable();
            $table->string('uniform_notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('user_clothing_sizes');
    }
};
