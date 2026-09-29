<?php

namespace App\Modules\Database\Services;

/**
 * حِزَم جاهزة تسحب كياناً كاملاً مع ملفاته، بدل أن يختار المستخدم جداوله يدوياً.
 *
 * «المهام مع ملفاتها» سؤالٌ طبيعي، لكن جوابه يحتاج معرفةً بأن المهام تسكن في
 * ثلاثة جداول وأن ملفاتها تسكن في جدول الدرايف مع بقية ملفات المؤسسة. الحزمة
 * تحمل هذه المعرفة بدل المستخدم.
 *
 * كل حزمة تعرّف جداولها، وقد تعرّف مرشِّح صفوف لجدول مشترك: المهام تأخذ من
 * drive_files صفوف المهام وحدها — بياناتٍ وملفاتٍ معاً — فلا تجرّ معها ملفات
 * الدرايف الشخصية.
 */
class BackupPresetRegistry
{
    private const PRESETS = [
        'tasks' => [
            'label' => 'المهام وملفاتها',
            'description' => 'المهام وسجل نشاطها وانتقالات مراحلها، ومرفقاتها في الدرايف وحدها.',
            'tables' => ['tasks', 'task_activities', 'task_stage_transitions', 'drive_files'],
            // ملفات المهمة تسكن في drive_files مع بقية ملفات المؤسسة، ويميّزها
            // task_id — فيُقصر الجدول على صفوفها دون سواها.
            'row_filters' => ['drive_files' => ['where_not_null' => ['task_id']]],
        ],
        'formal_correspondences' => [
            'label' => 'المراسلات الرسمية وملفاتها',
            'description' => 'المراسلات ووثائقها ومحطاتها وردود مراحلها والجهات الخارجية.',
            'tables' => [
                'formal_correspondences',
                'formal_correspondence_documents',
                'formal_correspondence_events',
                'formal_correspondence_stage_responses',
                'external_entities',
            ],
        ],
        'messages' => [
            'label' => 'الرسائل الداخلية وملفاتها',
            'description' => 'الرسائل وردودها ومرفقاتها.',
            'tables' => ['correspondences', 'correspondence_replies'],
        ],
        'hr' => [
            'label' => 'الموارد البشرية وملفاتها',
            'description' => 'الطلبات وسجل قراراتها وأرصدة الإجازات، ومرفقاتها ووثائقها المولَّدة.',
            'tables' => ['hr_requests', 'hr_request_actions', 'hr_leave_balances'],
        ],
        'fleet' => [
            'label' => 'فرع الآليات وملفاته',
            'description' => 'مهام العمل وسجل قراراتها، ومرفقاتها ووثائقها المولَّدة.',
            'tables' => ['fleet_missions', 'fleet_mission_actions'],
        ],
        'circulars' => [
            'label' => 'التعاميم وملفاتها',
            'description' => 'التعاميم ومستقبِليها ومرفقاتها.',
            'tables' => ['circulars', 'circular_recipients'],
        ],
        'forms' => [
            'label' => 'الفورمات وإجاباتها',
            'description' => 'القوالب وحقولها ومنشوراتها وإجابات الموظفين عليها.',
            'tables' => ['custom_forms', 'custom_form_fields', 'custom_form_publications', 'custom_form_submissions'],
        ],
        'drive' => [
            'label' => 'الدرايف كاملاً',
            'description' => 'كل الملفات والمجلدات ومشاركاتها وحصص الأدوار.',
            'tables' => ['drive_files', 'drive_folders', 'drive_file_shares', 'drive_folder_shares', 'role_drive_quotas'],
        ],
    ];

    public function __construct(private DatabaseTableRegistry $tables) {}

    public function has(string $key): bool
    {
        return isset(self::PRESETS[$key]);
    }

    /** جداول الحزمة الموجودة فعلاً في القاعدة — فحزمةٌ تذكر جدولاً محذوفاً لا تُفشل النسخة. */
    public function tables(string $key): array
    {
        $available = $this->tables->backupTables();

        return array_values(array_intersect(self::PRESETS[$key]['tables'] ?? [], $available));
    }

    public function rowFilters(string $key): array
    {
        return self::PRESETS[$key]['row_filters'] ?? [];
    }

    /** ما تعرضه الواجهة: الاسم والوصف والجداول المتاحة وعددها. */
    public function all(): array
    {
        $listed = [];

        foreach (self::PRESETS as $key => $preset) {
            $tables = $this->tables($key);
            if ($tables === []) {
                continue;
            }

            $listed[] = [
                'key' => $key,
                'label' => $preset['label'],
                'description' => $preset['description'],
                'tables' => $tables,
                'filtered' => array_keys($preset['row_filters'] ?? []),
            ];
        }

        return $listed;
    }
}
