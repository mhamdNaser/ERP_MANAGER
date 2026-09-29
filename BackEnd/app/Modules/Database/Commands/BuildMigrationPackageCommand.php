<?php

namespace App\Modules\Database\Commands;

use App\Modules\Database\Services\MigrationPackageService;
use Illuminate\Console\Command;

/**
 * بناء حزمة الترحيل من سطر الأوامر.
 *
 * موجود بجانب زرّ الواجهة لأن تبديل الخادم يجري عبر SSH عادةً، ولأن الحزمة
 * قد تطول على مؤسسة كبيرة الملفات فتتجاوز مهلة طلب HTTP.
 */
class BuildMigrationPackageCommand extends Command
{
    protected $signature = 'cnd:migration-package {--label= : لاحقة تُضاف إلى اسم الملف}';

    protected $description = 'يبني حزمة ترحيل كاملة: قاعدة البيانات وشجرة التخزين والقوالب وبطاقة تحقّق';

    public function handle(MigrationPackageService $packages): int
    {
        $this->info('جارٍ بناء حزمة الترحيل…');

        $result = $packages->build($this->option('label'));
        $manifest = $result['manifest'];

        $this->newLine();
        $this->line('  الملف        : ' . $result['file_name']);
        $this->line('  الحجم        : ' . $this->readable($result['size']));
        $this->line('  الإصدار      : ' . $manifest['app_version']);
        $this->line('  الترحيلات    : ' . $manifest['database']['migrations']['count']);
        $this->line('  ملفات التخزين: ' . $manifest['storage']['files'] . ' (' . $this->readable($manifest['storage']['bytes']) . ')');
        $this->line('  القوالب      : ' . $manifest['templates']['files'] . ' (' . $this->readable($manifest['templates']['bytes']) . ')');
        $this->line('  بصمة القاعدة : ' . $manifest['database']['dump']['sha256']);
        $this->newLine();
        $this->info('تمّت. تعليمات الاستعادة داخل الحزمة في README.txt');

        return self::SUCCESS;
    }

    private function readable(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1073741824, 2) . ' GB';
    }
}
