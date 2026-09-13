<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('custom_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('target_group')->default('branch_managers_heads_employees');
            $table->string('default_scope')->default('organization');
            $table->unsignedSmallInteger('default_duration_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('custom_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_form_id')->constrained('custom_forms')->cascadeOnDelete();
            $table->string('field_key');
            $table->string('label');
            $table->string('input_type');
            $table->json('options')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['custom_form_id', 'field_key']);
        });

        Schema::create('custom_form_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_form_id')->constrained('custom_forms')->cascadeOnDelete();
            $table->foreignId('issuer_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope')->default('organization');
            $table->string('target_group')->default('branch_managers_heads_employees');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('visible_from')->nullable();
            $table->timestamp('visible_until')->nullable();
            $table->string('status')->default('active');
            $table->text('message')->nullable();
            $table->timestamps();
        });

        Schema::create('custom_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_form_id')->constrained('custom_forms')->cascadeOnDelete();
            $table->foreignId('custom_form_publication_id')->constrained('custom_form_publications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('payload');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['custom_form_id', 'user_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_form_submissions');
        Schema::dropIfExists('custom_form_publications');
        Schema::dropIfExists('custom_form_fields');
        Schema::dropIfExists('custom_forms');
    }
};
