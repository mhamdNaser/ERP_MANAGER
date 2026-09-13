<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formal_correspondences', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('formal_correspondences')->nullOnDelete();
            $table->string('source_type')->nullable()->after('direction');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->string('source_label')->nullable()->after('source_id');
            $table->string('target_type')->nullable()->after('source_label');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            $table->string('target_label')->nullable()->after('target_id');
        });

        Schema::table('formal_correspondence_events', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('actor_id')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_id')->nullable()->after('assigned_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by_id');
            $table->foreignId('task_id')->nullable()->after('assigned_at')->constrained('tasks')->nullOnDelete();
        });

        // النوع "من مؤسسة خارجية لمؤسسة خارجية" لم يعد مدعوماً.
        DB::table('formal_correspondences')
            ->where('direction', 'external_to_external')
            ->update(['direction' => 'external_to_internal']);

        $this->backfillParties();
    }

    public function down(): void
    {
        Schema::table('formal_correspondence_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropConstrainedForeignId('assigned_by_id');
            $table->dropConstrainedForeignId('task_id');
            $table->dropColumn('assigned_at');
        });

        Schema::table('formal_correspondences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['source_type', 'source_id', 'source_label', 'target_type', 'target_id', 'target_label']);
        });
    }

    // يشتق الجهة المصدرة والجهة المخاطبة من الأعمدة القديمة للسجلات الموجودة.
    private function backfillParties(): void
    {
        DB::table('formal_correspondences')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $senderName = $row->sender_external_entity_id
                    ? DB::table('external_entities')->where('id', $row->sender_external_entity_id)->value('name')
                    : null;
                $recipientName = $row->recipient_external_entity_id
                    ? DB::table('external_entities')->where('id', $row->recipient_external_entity_id)->value('name')
                    : null;
                $departmentName = $row->recipient_department_id
                    ? DB::table('departments')->where('id', $row->recipient_department_id)->value('name')
                    : null;
                $branchName = $row->recipient_branch_id
                    ? DB::table('branches')->where('id', $row->recipient_branch_id)->value('name')
                    : null;

                $isIncoming = $row->direction === 'external_to_internal';
                $source = $isIncoming
                    ? ['type' => 'external_entity', 'id' => $row->sender_external_entity_id, 'label' => $senderName ?: 'جهة خارجية']
                    : ['type' => 'our_org', 'id' => null, 'label' => 'مؤسستنا'];

                if ($isIncoming) {
                    $target = ['type' => 'our_org', 'id' => null, 'label' => 'مؤسستنا'];
                } elseif ($row->direction === 'internal_to_external') {
                    $target = ['type' => 'external_entity', 'id' => $row->recipient_external_entity_id, 'label' => $recipientName ?: 'جهة خارجية'];
                } else {
                    $target = $row->recipient_department_id
                        ? ['type' => 'department', 'id' => $row->recipient_department_id, 'label' => $departmentName ?: 'قسم']
                        : ['type' => 'branch', 'id' => $row->recipient_branch_id, 'label' => $branchName ?: 'غير محددة'];
                }

                DB::table('formal_correspondences')->where('id', $row->id)->update([
                    'source_type' => $source['type'],
                    'source_id' => $source['id'],
                    'source_label' => $source['label'],
                    'target_type' => $target['type'],
                    'target_id' => $target['id'],
                    'target_label' => $target['label'],
                ]);
            }
        });
    }
};
