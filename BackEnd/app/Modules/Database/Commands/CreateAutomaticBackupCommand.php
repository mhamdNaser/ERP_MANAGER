<?php

namespace App\Modules\Database\Commands;

use App\Modules\Database\Services\DatabaseBackupService;
use Illuminate\Console\Command;

/**
 * نسخة احتياطية كاملة يومية تلقائية (صيغة backup الحقيقية القابلة للاستعادة
 * عبر pg_restore)، مع حذف النسخ الأقدم من مدة الاحتفاظ لتفادي نمو التخزين
 * دون حد. راجع routes/console.php لموعد الجدولة.
 */
class CreateAutomaticBackupCommand extends Command
{
    protected $signature = 'database:auto-backup {--keep-days=14 : عدد الأيام للاحتفاظ بالنسخ التلقائية}';

    protected $description = 'ينشئ نسخة احتياطية كاملة تلقائية لقاعدة البيانات (صيغة backup) ويحذف القديم منها';

    public function handle(DatabaseBackupService $backups): int
    {
        $backup = $backups->createBackup('auto', 'backup', null);
        $this->info("تم إنشاء نسخة تلقائية: {$backup['file_name']} ({$backup['size']} bytes)");

        $keepDays = (int) $this->option('keep-days');
        $cutoff = now()->subDays($keepDays);
        $deleted = 0;

        foreach ($backups->listBackups() as $item) {
            if (! str_ends_with($item['file_name'], '-auto.backup')) {
                continue;
            }
            if (\Illuminate\Support\Carbon::parse($item['created_at'])->lt($cutoff)) {
                $backups->delete($item['file_name']);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->info("تم حذف {$deleted} نسخة تلقائية أقدم من {$keepDays} يومًا.");
        }

        return self::SUCCESS;
    }
}
