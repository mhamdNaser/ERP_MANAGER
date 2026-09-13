<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('department_id')->constrained();
            $table->string('type');
            $table->date('period_start');
            $table->date('period_end')->nullable();
            $table->string('title');
            $table->text('summary');
            $table->text('achievements')->nullable();
            $table->text('challenges')->nullable();
            $table->text('next_steps')->nullable();
            $table->string('status')->default('draft');
            $table->string('current_reviewer_role')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
