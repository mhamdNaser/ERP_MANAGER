<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل انتقالات المهمة بين المراحل: صف لكل حركة، بتاريخها ووقتها ومدة بقاء
 * المهمة في المرحلة السابقة. سجل الحركات (task_activities) يبقى للعرض
 * البشري، أما هذا الجدول فهو مصدر لوحة الإحصائيات: منه تُحسب سرعة كل موظف
 * ومدة كل مرحلة وعدد مرات إعادة المهمة من التواصل إلى التنفيذ.
 *
 * صاحب المهمة وموظف التواصل يُلتقطان لحظة الانتقال لأن نسبة التأخير تُحسب
 * على من كان مسؤولًا فعلًا حينها، لا على من تحمل المهمة اسمه اليوم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_stage_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('communication_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->unsignedBigInteger('seconds_in_previous')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'created_at']);
            $table->index(['assignee_id', 'from_status']);
            $table->index(['communication_user_id', 'from_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_stage_transitions');
    }
};
