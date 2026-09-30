<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط التبويب بقسم (كل منتسبيه) أو بشخص بعينه. كل صف يحمل أحدهما لا كليهما،
 * ومستواه «اطلاع» أو «إدارة» — وما يفتحه كل مستوى معرَّف في config/tab_access.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tab_access_grants', function (Blueprint $table) {
            $table->id();
            $table->string('tab')->index();
            $table->foreignId('department_id')->nullable()->constrained('departments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('level')->default('view');                  // view | manage
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tab', 'department_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tab_access_grants');
    }
};
