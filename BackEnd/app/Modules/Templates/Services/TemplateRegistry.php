<?php

namespace App\Modules\Templates\Services;

use InvalidArgumentException;
use ZipArchive;

/**
 * كتالوج قوالب Word التي تُبنى منها وثائق النظام.
 *
 * مسار كل قالب مصدره config/document_templates.php ولا يُكرَّر هنا؛ ما يُضاف
 * في هذا الكتالوج هو ما يحتاجه من يدير القوالب: اسمٌ عربي، ومجموعة، ووصف،
 * وقائمة الحقول التي تملؤها الخدمة المولِّدة — كي يُنبَّه من يرفع قالباً
 * ناقص الحقول قبل أن تخرج وثيقةٌ ناقصة إلى الناس.
 */
class TemplateRegistry
{
    /** حقول الكتب الرسمية المشتركة: ترويسة ورقم وتاريخان وتوقيع. */
    private const LETTER_FIELDS = [
        'registry_number', 'gregorian_date', 'hijri_date',
        'recipient', 'body', 'qr_code', 'signer_name', 'signer_role', 'signature',
    ];

    private const CATALOG = [
        'formal_correspondences.stage_internal_letter' => [
            'group' => 'المراسلات الرسمية',
            'label' => 'كتاب داخلي',
            'description' => 'الكتاب الصادر عن محطة المراسلة الداخلية.',
            'fields' => self::LETTER_FIELDS,
        ],
        'formal_correspondences.stage_external_reply' => [
            'group' => 'المراسلات الرسمية',
            'label' => 'رد على جهة خارجية',
            'description' => 'كتاب الرد المرسل إلى جهة خارج الإدارة.',
            'fields' => self::LETTER_FIELDS,
        ],
        'formal_correspondences.stage_study_report' => [
            'group' => 'المراسلات الرسمية',
            'label' => 'تقرير دراسة',
            'description' => 'تقرير محطة الدراسة، ويضيف حقل التوصية.',
            'fields' => [...self::LETTER_FIELDS, 'recommendation'],
        ],
        'formal_correspondences.stage_execution_report' => [
            'group' => 'المراسلات الرسمية',
            'label' => 'تقرير تنفيذ',
            'description' => 'تقرير محطة التنفيذ، ويضيف حقل التوصية.',
            'fields' => [...self::LETTER_FIELDS, 'recommendation'],
        ],

        'hr.leave_request' => [
            'group' => 'الموارد البشرية',
            'label' => 'نموذج إجازة إدارية',
            'description' => 'النموذج الرسمي الذي تُطبع عليه الإجازة الإدارية المعتمدة.',
            'fields' => [
                'registry_number', 'gregorian_date', 'hijri_date',
                'employee_name', 'job_title', 'employee_number',
                'days', 'reason', 'start_date', 'end_date',
            ],
        ],
        'hr.hourly_leave_request' => [
            'group' => 'الموارد البشرية',
            'label' => 'نموذج إجازة ساعية',
            'description' => 'النموذج الرسمي الذي تُطبع عليه المغادرة الساعية المعتمدة.',
            'fields' => [
                'registry_number', 'gregorian_date', 'hijri_date',
                'employee_name', 'job_title', 'employee_number',
                'start_time', 'end_time', 'date',
            ],
        ],
        'hr.request_approval' => [
            'group' => 'الموارد البشرية',
            'label' => 'كتاب اعتماد طلب',
            'description' => 'الكتاب العام لبقية طلبات الموارد البشرية، ومرجعٌ احتياطي لما لا نموذج له.',
            'fields' => [...self::LETTER_FIELDS, 'status'],
        ],

        'fleet.mission_approval' => [
            'group' => 'فرع الآليات',
            'label' => 'كتاب اعتماد مهمة عمل',
            'description' => 'وثيقة المهمة المعتمدة. ما لم يوجد، يُستعمل كتاب اعتماد طلب الموارد البشرية.',
            'fields' => [...self::LETTER_FIELDS, 'status'],
        ],

        'messages.message_export' => [
            'group' => 'الرسائل الداخلية',
            'label' => 'تصدير رسالة داخلية',
            'description' => 'وثيقة الرسالة المصدَّرة، وتضيف حقلي المرسِل والمرسَل إليه.',
            'fields' => [...self::LETTER_FIELDS, 'source', 'target'],
        ],

        'reports.approved_report' => [
            'group' => 'التقارير',
            'label' => 'تقرير معتمد',
            'description' => 'وثيقة التقرير بعد اعتماده النهائي.',
            'fields' => [...self::LETTER_FIELDS, 'status'],
        ],

        'custom_forms.submission_export' => [
            'group' => 'الفورمات',
            'label' => 'تصدير إجابة فورم',
            'description' => 'وثيقة إجابة الفورم المصدَّرة.',
            'fields' => self::LETTER_FIELDS,
        ],
    ];

    public function keys(): array
    {
        return array_keys(self::CATALOG);
    }

    public function has(string $key): bool
    {
        return isset(self::CATALOG[$key]);
    }

    public function entry(string $key): array
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("قالب غير معروف: {$key}");
        }

        return self::CATALOG[$key];
    }

    /** المسار المطلق للقالب كما تعرّفه config/document_templates.php. */
    public function path(string $key): ?string
    {
        $this->entry($key);

        $path = config('document_templates.' . $key);

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * النسخة الفارغة من النموذج — اصطلاحاً في مجلد blank بجانب القالب.
     * موجودة لنماذج الإجازات الورقية التي تُطبع أحياناً لتُملأ بالقلم.
     */
    public function blankPath(string $key): ?string
    {
        $path = $this->path($key);
        if (! $path) {
            return null;
        }

        $blank = dirname($path) . DIRECTORY_SEPARATOR . 'blank' . DIRECTORY_SEPARATOR . basename($path);

        return is_file($blank) ? $blank : null;
    }

    /** الحقول الموجودة فعلاً داخل ملف docx. */
    public function placeholdersIn(?string $path): array
    {
        if (! $path || ! is_file($path)) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $found = [];
        for ($index = 0; $index < $zip->numFiles; $index += 1) {
            $name = $zip->getNameIndex($index);
            if (! $name || ! preg_match('/^word\/.*\.xml$/', $name)) {
                continue;
            }

            $xml = (string) $zip->getFromName($name);
            // الحقل قد ينقسم بين عدة runs في Word، فيُزال الترميز قبل البحث.
            if (preg_match_all('/\{\{([a-z_]+)\}\}/', strip_tags($xml), $matches)) {
                $found = array_merge($found, $matches[1]);
            }
        }

        $zip->close();
        sort($found);

        return array_values(array_unique($found));
    }

    /** بطاقة القالب كاملةً: التعريف + حالة الملف على القرص. */
    public function describe(string $key): array
    {
        $entry = $this->entry($key);
        $path = $this->path($key);
        $exists = $path && is_file($path);
        $present = $this->placeholdersIn($path);

        return [
            'key' => $key,
            'group' => $entry['group'],
            'label' => $entry['label'],
            'description' => $entry['description'],
            'file_name' => $path ? basename($path) : null,
            'exists' => $exists,
            'size' => $exists ? filesize($path) : null,
            'updated_at' => $exists ? date('Y-m-d H:i:s', filemtime($path)) : null,
            'fields' => $entry['fields'],
            'placeholders' => $present,
            'missing' => array_values(array_diff($entry['fields'], $present)),
            'has_blank' => (bool) $this->blankPath($key),
        ];
    }

    public function all(): array
    {
        return array_map(fn (string $key) => $this->describe($key), $this->keys());
    }
}
