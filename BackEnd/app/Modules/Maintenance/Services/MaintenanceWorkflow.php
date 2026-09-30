<?php

namespace App\Modules\Maintenance\Services;

/**
 * سير عمل القطعة في قسم الصيانة، والمصدر الوحيد لحالاتها وانتقالاتها.
 *
 * تدخل القطعة المستودع «ضمن المخزن»، ثم يفحصها الفني: الشغّالة «جاهزة»،
 * والمعطّلة «تحت الصيانة» ومنها إلى «تمت صيانتها» أو «تالفة». الواجهة لا
 * ترسم إلا ما تنشره transitions() هنا.
 */
class MaintenanceWorkflow
{
    public const STATUSES = ['in_stock', 'under_maintenance', 'repaired', 'ready', 'damaged'];

    /** ما يصلح للاستعمال — يُقاس عليه تنبيه نقص المخزون. */
    public const USABLE = ['in_stock', 'ready', 'repaired'];

    public const LABELS = [
        'in_stock' => 'ضمن المخزن',
        'under_maintenance' => 'تحت الصيانة',
        'repaired' => 'تمت صيانتها',
        'ready' => 'جاهزة',
        'damaged' => 'تالفة',
    ];

    public const KIND_LABELS = [
        'receive' => 'إدخال إلى المستودع',
        'move' => 'نقل حالة',
        'issue' => 'صرف خارج القسم',
    ];

    private const TRANSITIONS = [
        'in_stock' => ['under_maintenance', 'ready', 'damaged'],
        'under_maintenance' => ['repaired', 'damaged', 'in_stock'],
        'repaired' => ['ready', 'in_stock', 'under_maintenance'],
        'ready' => ['in_stock', 'under_maintenance', 'damaged'],
        // التالفة لا تعود إلا بمحاولة صيانة جديدة.
        'damaged' => ['under_maintenance'],
    ];

    public static function transitions(): array
    {
        return self::TRANSITIONS;
    }

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isStatus(?string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public static function column(string $status): string
    {
        abort_unless(self::isStatus($status), 422, 'حالة غير معروفة.');

        return "qty_{$status}";
    }

    public static function label(?string $status): string
    {
        return self::LABELS[$status] ?? '—';
    }
}
