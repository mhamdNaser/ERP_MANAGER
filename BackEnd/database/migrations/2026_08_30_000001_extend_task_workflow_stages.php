<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * توسيع مسار المهمة إلى سبع مراحل:
 *   مؤرشفة ← مخطط لها ← قيد التنفيذ ⇄ التواصل ← التدقيق ← الاعتماد
 * (مع الإلغاء خارج المسار).
 *
 * الترقية إضافية بالكامل: المراحل القديمة لم تُحذف ولم يُعد تسميتها، وإنما
 * أُدرجت مراحل جديدة بينها. حالة `completed` تبقى كما هي في القاعدة وهي
 * المرحلة النهائية نفسها التي صارت تُسمّى في الواجهة «الاعتماد» لأنها لم تعد
 * تُبلغ إلا بعد التدقيق — فلا صف واحد من المهام أو من سجل الحركات يُعدَّل.
 *
 * ما يضيفه هذا الملف: موظف التواصل الحالي، ووقت دخول المرحلة الحالية، ووقت
 * أول انتقال إلى التنفيذ. الأعمدة الثلاثة nullable وتُملأ للمهام القائمة
 * تقديرًا من `updated_at` لأن أوقاتها الحقيقية لم تكن مسجَّلة قبل اليوم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('communication_user_id')->nullable()->after('assignee_id')->constrained('users')->nullOnDelete();
            $table->timestamp('stage_entered_at')->nullable();
            $table->timestamp('started_at')->nullable();
        });

        DB::table('tasks')->whereNull('stage_entered_at')->update(['stage_entered_at' => DB::raw('updated_at')]);
        DB::table('tasks')->whereIn('status', ['in_progress', 'communication', 'review', 'completed'])
            ->whereNull('started_at')->update(['started_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        // المهام التي بلغت مرحلة لم تكن موجودة قبل الترقية تعود إلى أقرب حالة
        // قديمة تعبّر عنها، وإلا بقيت بقيمة لا تعرفها النسخة السابقة.
        DB::table('tasks')->whereIn('status', ['communication', 'review'])->update(['status' => 'in_progress']);
        DB::table('tasks')->where('status', 'archived')->update(['status' => 'planned']);

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('communication_user_id');
            $table->dropColumn(['stage_entered_at', 'started_at']);
        });
    }
};
