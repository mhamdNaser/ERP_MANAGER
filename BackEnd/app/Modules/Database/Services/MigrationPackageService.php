<?php

namespace App\Modules\Database\Services;

use App\Modules\Database\Repositories\Interfaces\DatabaseRepositoryInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * حزمة ترحيل كاملة: كل ما يلزم لإقامة النظام على خادم جديد.
 *
 * تختلف عن النسخة الاحتياطية المعتادة في مصدر الملفات: تلك تجمع ما تشير إليه
 * أعمدة المسارات في القاعدة، وهذه تأخذ **شجرة التخزين كما هي**. الفرق ليس
 * نظرياً: قوالب Word في resources/templates لا يشير إليها أي صف، فلا تدخل
 * النسخة المعتادة أبداً — ويكتشف ذلك من ينقل الخادم بعد فوات الأوان.
 *
 * والحزمة تحمل بطاقةً (manifest) فيها إصدار النظام وعدد الترحيلات المنفَّذة
 * وبصمة ملف القاعدة، كي يتحقق من يستعيدها أنه يستعيدها على نظامٍ مطابق.
 */
class MigrationPackageService
{
    /** لا تدخل الحزمة: نسخ احتياطية سابقة (تكرارٌ ثقيل) وملفات مؤقتة. */
    private const SKIPPED_DIRECTORIES = ['database-backups', 'tmp'];

    /**
     * الجذران قابلان للحقن كي تعمل الاختبارات على مجلد مؤقت بدل أرشفة تخزين
     * المشروع الحقيقي في كل تشغيل.
     */
    public function __construct(
        private DatabaseTableRegistry $tables,
        private PgDumpService $pgDump,
        private DatabaseRepositoryInterface $database,
        private ?string $storageRoot = null,
        private ?string $templatesRoot = null,
    ) {}

    /**
     * @return array{file_name: string, path: string, size: int, manifest: array}
     */
    public function build(?string $label = null): array
    {
        $disk = $this->disk();
        $directory = $this->directory();
        if (! is_dir(Storage::disk($disk)->path($directory))) {
            Storage::disk($disk)->makeDirectory($directory);
        }

        $stamp = now()->format('Y-m-d_His');
        $fileName = 'cnd-migration-' . $stamp . ($label ? '-' . $label : '') . '.zip';
        $zipPath = Storage::disk($disk)->path("{$directory}/{$fileName}");
        $dumpPath = Storage::disk($disk)->path("{$directory}/migration-{$stamp}.backup");

        $this->pgDump->dump($dumpPath, 'backup', null, $this->tables->maintenanceConnection());

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($dumpPath);
            throw new RuntimeException("تعذّر إنشاء حزمة الترحيل: {$zipPath}");
        }

        $zip->addFile($dumpPath, 'database.backup');

        $storage = $this->addTree($zip, $this->storageRoot ?? storage_path('app'), 'storage', self::SKIPPED_DIRECTORIES);
        $templates = $this->addTree($zip, $this->templatesRoot ?? resource_path('templates'), 'templates', []);

        $manifest = $this->manifest($dumpPath, $storage, $templates);
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('README.txt', $this->readme($manifest));

        $zip->close();
        @unlink($dumpPath);

        return [
            'file_name' => $fileName,
            'path' => "{$directory}/{$fileName}",
            'size' => filesize($zipPath) ?: 0,
            'manifest' => $manifest,
        ];
    }

    /**
     * يضيف شجرة مجلد كاملةً إلى الأرشيف.
     *
     * @return array{files: int, bytes: int, skipped: int}
     */
    private function addTree(ZipArchive $zip, string $root, string $prefix, array $skipped): array
    {
        if (! is_dir($root)) {
            return ['files' => 0, 'bytes' => 0, 'skipped' => 0];
        }

        $files = 0;
        $bytes = 0;
        $ignored = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));

            // مجلدٌ مستثنى يُستثنى هو وكل ما تحته.
            $head = explode('/', $relative)[0];
            $second = explode('/', $relative)[1] ?? null;
            if (in_array($head, $skipped, true) || ($second !== null && in_array($second, $skipped, true))) {
                if ($item->isFile()) {
                    $ignored++;
                }
                continue;
            }

            if ($item->isDir()) {
                $zip->addEmptyDir("{$prefix}/{$relative}");
                continue;
            }

            if (! $item->isReadable()) {
                $ignored++;
                continue;
            }

            $zip->addFile($item->getPathname(), "{$prefix}/{$relative}");
            $files++;
            $bytes += $item->getSize();
        }

        return ['files' => $files, 'bytes' => $bytes, 'skipped' => $ignored];
    }

    private function manifest(string $dumpPath, array $storage, array $templates): array
    {
        $tables = [];
        foreach ($this->tables->backupTables() as $table) {
            $tables[$table] = $this->database->allRows($table)->count();
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'app_version' => $this->appVersion(),
            'app_name' => config('app.name'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => [
                'name' => $this->database->databaseName(),
                'driver' => $this->database->driver(),
                'dump' => ['bytes' => filesize($dumpPath) ?: 0, 'sha256' => hash_file('sha256', $dumpPath)],
                'migrations' => $this->migrations(),
                'tables' => $tables,
            ],
            'storage' => $storage,
            'templates' => $templates,
        ];
    }

    /** رقم الإصدار من ملف VERSION في جذر المشروع — نفسه الذي يوسمه سكربت الإصدارات. */
    private function appVersion(): string
    {
        $path = base_path('../VERSION');

        return is_file($path) ? trim((string) file_get_contents($path)) : 'unknown';
    }

    /** عدد الترحيلات المنفَّذة وآخرها — بهما يُعرف أن الخادمين على البنية نفسها. */
    private function migrations(): array
    {
        if (! $this->database->hasTable('migrations')) {
            return ['count' => 0, 'latest' => null];
        }

        $rows = $this->database->allRows('migrations');

        return [
            'count' => $rows->count(),
            'latest' => $rows->sortByDesc('id')->first()->migration ?? null,
        ];
    }

    private function readme(array $manifest): string
    {
        $lines = [
            'حزمة ترحيل ' . $manifest['app_name'] . ' — الإصدار ' . $manifest['app_version'],
            'أُنشئت: ' . $manifest['generated_at'],
            '',
            'المحتوى:',
            '  database.backup   نسخة pg_dump كاملة (صيغة custom)',
            '  storage/          شجرة storage/app كما هي، بلا النسخ الاحتياطية والملفات المؤقتة',
            '  templates/        قوالب Word ونسخها السابقة',
            '  manifest.json     بطاقة الحزمة: الإصدار والترحيلات وعدد صفوف كل جدول وبصمة الملف',
            '',
            'الاستعادة على خادم جديد:',
            '  1) انشر الكود على الخادم الجديد بالإصدار نفسه: ' . $manifest['app_version'],
            '  2) تحقّق أن عدد الترحيلات مطابق: ' . $manifest['database']['migrations']['count'],
            '  3) فُكّ الحزمة، وأعد القاعدة:',
            '     pg_restore --clean --if-exists --no-owner -d ' . $manifest['database']['name'] . ' database.backup',
            '  4) انسخ storage/ فوق BackEnd/storage/app و templates/ فوق BackEnd/resources/templates',
            '  5) chown -R www-data:www-data على الاثنين، ثم:',
            '     php artisan storage:link && php artisan config:cache && php artisan route:cache',
            '',
            'تحقّق من سلامة ملف القاعدة قبل الاستعادة:',
            '  sha256sum database.backup   ويجب أن يطابق:',
            '  ' . $manifest['database']['dump']['sha256'],
        ];

        return implode("\n", $lines) . "\n";
    }

    private function disk(): string
    {
        return env('BACKUP_STORAGE_DISK', 'local');
    }

    private function directory(): string
    {
        return trim((string) env('BACKUP_DIRECTORY', 'database-backups'), '/');
    }
}
