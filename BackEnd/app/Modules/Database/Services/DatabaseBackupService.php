<?php

namespace App\Modules\Database\Services;

use App\Modules\Database\Repositories\Interfaces\DatabaseRepositoryInterface;
use Illuminate\Support\Facades\Storage;

class DatabaseBackupService
{
    private const FORMATS = ['json', 'sql', 'backup'];
    private const EXTENSIONS = ['json' => 'json', 'sql' => 'sql', 'backup' => 'backup'];

    public function __construct(
        private DatabaseTableRegistry $tables,
        private PgDumpService $pgDump,
        private BackupFileBundler $fileBundler,
        private DatabaseRepositoryInterface $database,
    ) {}

    public function listBackups(): array
    {
        $disk = $this->disk();
        $directory = $this->directory();

        return collect(Storage::disk($disk)->files($directory))
            ->filter(fn (string $file) => $this->formatFor($file) !== null)
            ->map(fn (string $file) => $this->metadata($file))
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    /** @return array{file_name: string, size: int, created_at: string, download_url: string, kind: string, format: ?string, bundled: bool, files?: array} */
    public function createBackup(string $kind = 'internal', string $format = 'json', ?array $tables = null, bool $bundleFiles = false): array
    {
        abort_unless(in_array($format, self::FORMATS, true), 422, "صيغة تصدير غير مدعومة: {$format}");

        $disk = $this->disk();
        $directory = $this->directory();
        // Only create the directory when it is truly missing. Calling
        // makeDirectory() on an existing directory re-applies the disk's
        // (private => 0700) visibility via chmod, which both fails when the
        // web user is not the owner and strips group access the deploy user
        // needs. Storage::exists() reports files only, so probe the real path.
        if (! is_dir(Storage::disk($disk)->path($directory))) {
            Storage::disk($disk)->makeDirectory($directory);
        }

        $scopedTables = $this->scopedTables($tables);
        $timestamp = now()->format('Y-m-d_His');
        $extension = self::EXTENSIONS[$format];
        $fileName = "cnd-backup-{$timestamp}-{$kind}.{$extension}";
        $absolutePath = Storage::disk($disk)->path("{$directory}/{$fileName}");

        if ($format === 'json') {
            $this->writeJson($absolutePath, $kind, $scopedTables);
        } else {
            $this->pgDump->dump($absolutePath, $format, $scopedTables, $this->tables->maintenanceConnection());
        }

        // جدول الجداول المطلوبة (أو null = الكل) بجانب كل ملف، بصيغة مستقلة عن
        // محتوى الملف نفسه — يتيح لواجهة الاستعادة معرفة نطاق نسخ SQL/Backup
        // دون الحاجة لتحليل pg_dump أو استدعاء pg_restore --list.
        file_put_contents("{$absolutePath}.meta", json_encode(['tables' => $scopedTables], JSON_UNESCAPED_UNICODE));

        if (! $bundleFiles) {
            return $this->metadata("{$directory}/{$fileName}");
        }

        $zipFileName = "{$fileName}.zip";
        $zipAbsolutePath = Storage::disk($disk)->path("{$directory}/{$zipFileName}");
        $filesSummary = $this->fileBundler->bundle(
            $zipAbsolutePath, $absolutePath, $extension, $scopedTables, $this->tables->maintenanceConnection(),
        );
        @unlink($absolutePath);
        @unlink("{$absolutePath}.meta");

        return $this->metadata("{$directory}/{$zipFileName}") + ['files' => $filesSummary];
    }

    public function delete(string $fileName): bool
    {
        if (! $this->exists($fileName)) {
            return false;
        }

        $metaPath = $this->pathFor($fileName) . '.meta';
        if (Storage::disk($this->disk())->exists($metaPath)) {
            Storage::disk($this->disk())->delete($metaPath);
        }

        return Storage::disk($this->disk())->delete($this->pathFor($fileName));
    }

    public function pathFor(string $fileName): string
    {
        // basename() strips any directory component, hardening against
        // path traversal even if the route constraint is ever loosened.
        return "{$this->directory()}/" . basename($fileName);
    }

    public function exists(string $fileName): bool
    {
        return Storage::disk($this->disk())->exists($this->pathFor($fileName));
    }

    public function absolutePath(string $fileName): string
    {
        return Storage::disk($this->disk())->path($this->pathFor($fileName));
    }

    public function downloadName(string $fileName): string
    {
        return $fileName;
    }

    public function formatOfFile(string $fileName): ?string
    {
        return $this->formatFor($fileName);
    }

    /** ملفات النسخ المُحزَّمة (.zip) تضم الملفات المرفقة إلى جانب بيانات الجداول. */
    public function isBundled(string $fileName): bool
    {
        return str_ends_with($fileName, '.zip');
    }

    /** أسماء الجداول (وعددها إن توفّر) داخل نسخة — لواجهة اختيار جداول الاستعادة. */
    public function tablesInBackup(string $fileName): array
    {
        abort_unless($this->exists($fileName), 404);
        $format = $this->formatFor($fileName);

        if ($format === 'json') {
            $payload = $this->readJsonPayload($fileName);

            return collect($payload['tables'] ?? [])
                ->map(fn ($rows, $table) => ['table' => $table, 'rows' => is_countable($rows) ? count($rows) : 0])
                ->values()
                ->all();
        }

        $tables = $this->dumpTablesFor($fileName) ?? $this->tables->backupTables();

        return collect($tables)->map(fn (string $table) => ['table' => $table, 'rows' => null])->values()->all();
    }

    /** صفوف جداول محددة من نسخة JSON — تُستخدم أثناء الاستعادة الفعلية. */
    public function payloadTables(string $fileName, array $tables): array
    {
        $payload = $this->readJsonPayload($fileName);
        $data = $payload['tables'] ?? [];

        return collect($tables)->mapWithKeys(fn (string $table) => [$table => $data[$table] ?? []])->all();
    }

    /** نطاق الجداول المسجَّل مع نسخة SQL/Backup وقت إنشائها — null يعني كل الجداول. */
    public function dumpTablesFor(string $fileName): ?array
    {
        if ($this->isBundled($fileName)) {
            $meta = $this->fileBundler->readMeta($this->absolutePath($fileName), self::EXTENSIONS[$this->formatFor($fileName)]);

            return $meta['tables'] ?? null;
        }

        $metaPath = $this->pathFor($fileName) . '.meta';
        if (! Storage::disk($this->disk())->exists($metaPath)) {
            return null;
        }

        $meta = json_decode(Storage::disk($this->disk())->get($metaPath), true) ?? [];

        return $meta['tables'] ?? null;
    }

    private function readJsonPayload(string $fileName): array
    {
        abort_unless($this->formatFor($fileName) === 'json' && $this->exists($fileName), 404);

        if ($this->isBundled($fileName)) {
            $json = $this->fileBundler->readDump($this->absolutePath($fileName), 'json');

            return json_decode((string) $json, true) ?? [];
        }

        return json_decode(Storage::disk($this->disk())->get($this->pathFor($fileName)), true) ?? [];
    }

    private function scopedTables(?array $tables): ?array
    {
        if ($tables === null || $tables === []) {
            return null;
        }

        $allowed = $this->tables->backupTables();
        $invalid = array_diff($tables, $allowed);
        abort_if($invalid !== [], 422, 'جدول/جداول غير معروفة: ' . implode(', ', $invalid));

        return array_values(array_unique($tables));
    }

    private function writeJson(string $absolutePath, string $kind, ?array $tables): void
    {
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'kind' => $kind,
            'connection' => $this->database->connectionName(),
            'database' => $this->database->databaseName(),
            'tables' => $this->tableData($tables),
        ];

        file_put_contents($absolutePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function tableData(?array $only): array
    {
        $tables = $only ?? $this->tables->backupTables();

        return collect($tables)->mapWithKeys(fn (string $table) => [$table => $this->database->allRows($table)])->all();
    }

    private function formatFor(string $file): ?string
    {
        $stripped = str_ends_with($file, '.zip') ? substr($file, 0, -4) : $file;

        return match (true) {
            str_ends_with($stripped, '.json') => 'json',
            str_ends_with($stripped, '.sql') => 'sql',
            str_ends_with($stripped, '.backup') => 'backup',
            default => null,
        };
    }

    private function metadata(string $path): array
    {
        $disk = $this->disk();
        $fileName = basename($path);

        return [
            'file_name' => $fileName,
            'size' => Storage::disk($disk)->size($path),
            'created_at' => now()->setTimestamp(Storage::disk($disk)->lastModified($path))->toIso8601String(),
            'download_url' => url("/api/database-backups/{$fileName}/download"),
            'kind' => match (true) {
                str_contains($fileName, '-external') => 'external',
                str_contains($fileName, '-auto') => 'auto',
                default => 'internal',
            },
            'format' => $this->formatFor($fileName),
            'bundled' => $this->isBundled($fileName),
        ];
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
