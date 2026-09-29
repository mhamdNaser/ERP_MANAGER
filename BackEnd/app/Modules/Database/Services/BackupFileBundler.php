<?php

namespace App\Modules\Database\Services;

use App\Modules\Database\Repositories\Interfaces\DatabaseRepositoryInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Wraps a database backup dump (in whatever format DatabaseBackupService
 * already produced — json/sql/backup) together with the actual attachment
 * files it references (drive files, correspondence/HR documents,
 * signatures, ...) into a single .zip package, and unpacks one back for
 * restore. The dump itself is untouched — this only adds the "files/" side
 * of the package, re-querying the DB directly for attachment path columns
 * (independent of the dump's own serialization format).
 */
class BackupFileBundler
{
    public function __construct(
        private BackupAttachmentRegistry $attachments,
        private DatabaseRepositoryInterface $database,
    ) {}

    /**
     * @return array{entries: int, bundled: int, missing: int}
     */
    public function bundle(
        string $zipAbsolutePath,
        string $dumpAbsolutePath,
        string $dumpExtension,
        ?array $scopedTables,
        ?string $connection = null,
        array $rowFilters = [],
    ): array {
        $zip = new ZipArchive();
        if ($zip->open($zipAbsolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("تعذّر إنشاء أرشيف النسخة الاحتياطية: {$zipAbsolutePath}");
        }

        $zip->addFile($dumpAbsolutePath, "data.{$dumpExtension}");
        if (is_file("{$dumpAbsolutePath}.meta")) {
            $zip->addFile("{$dumpAbsolutePath}.meta", "data.{$dumpExtension}.meta");
        }

        $pathColumns = $this->attachments->pathColumns($connection);
        $tables = $scopedTables ?? array_keys($pathColumns);
        $manifest = ['generated_at' => now()->toIso8601String(), 'entries' => []];
        $bundled = 0;
        $missing = 0;

        foreach ($tables as $table) {
            foreach ($pathColumns[$table] ?? [] as $column) {
                // مرشِّح الحزمة يقصر الملفات على صفوف كيانها: ملفات المهام
                // وحدها من drive_files لا ملفات المؤسسة كلها.
                $values = $this->database->columnValues($table, $column, $connection, $rowFilters[$table] ?? []);
                foreach ($values as $relativePath) {
                    if (! is_string($relativePath) || $relativePath === '') {
                        continue;
                    }
                    $entry = $this->addFile($zip, $table, $column, $relativePath);
                    $manifest['entries'][] = $entry;
                    $entry['bundled'] ? $bundled++ : $missing++;
                }
            }
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        return ['entries' => count($manifest['entries']), 'bundled' => $bundled, 'missing' => $missing];
    }

    /** Reads the dump file out of a bundled backup without extracting the whole archive. */
    public function readDump(string $zipAbsolutePath, string $dumpExtension): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($zipAbsolutePath) !== true) {
            throw new RuntimeException("تعذّرت قراءة أرشيف النسخة الاحتياطية: {$zipAbsolutePath}");
        }
        $contents = $zip->getFromName("data.{$dumpExtension}");
        $zip->close();

        return $contents === false ? null : $contents;
    }

    public function readMeta(string $zipAbsolutePath, string $dumpExtension): ?array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipAbsolutePath) !== true) {
            return null;
        }
        $contents = $zip->getFromName("data.{$dumpExtension}.meta");
        $zip->close();

        return $contents === false ? null : (json_decode($contents, true) ?? null);
    }

    /** Extracts a bundled backup to a fresh private tmp directory and returns its path + parsed manifest. */
    public function extract(string $zipAbsolutePath): array
    {
        $extractDir = storage_path('app/private/tmp/db-restore-'.Str::uuid());
        if (! is_dir($extractDir) && ! mkdir($extractDir, 0700, true) && ! is_dir($extractDir)) {
            throw new RuntimeException("تعذّر إنشاء مجلد مؤقت للاستعادة: {$extractDir}");
        }

        $zip = new ZipArchive();
        if ($zip->open($zipAbsolutePath) !== true) {
            throw new RuntimeException("تعذّرت قراءة أرشيف النسخة الاحتياطية: {$zipAbsolutePath}");
        }
        $zip->extractTo($extractDir);
        $zip->close();

        $manifestPath = "{$extractDir}/manifest.json";
        $manifest = is_file($manifestPath) ? (json_decode(file_get_contents($manifestPath), true) ?? []) : [];

        return ['dir' => $extractDir, 'manifest' => $manifest];
    }

    /**
     * Copies the extracted files whose table is in $tables back onto their
     * disks. Best-effort: a missing/unreadable file is reported, never fatal
     * — the caller decides whether that should block anything.
     *
     * @return array{restored: int, skipped: int, errors: array<int, string>}
     */
    public function restoreFiles(string $extractDir, array $manifest, array $tables): array
    {
        $restored = 0;
        $skipped = 0;
        $errors = [];

        foreach ($manifest['entries'] ?? [] as $entry) {
            if (! in_array($entry['table'], $tables, true) || ! ($entry['bundled'] ?? false)) {
                $skipped++;
                continue;
            }

            $source = "{$extractDir}/files/{$entry['disk']}/{$entry['relative_path']}";
            if (! is_file($source)) {
                $errors[] = "{$entry['table']}.{$entry['relative_path']}: الملف غير موجود داخل الأرشيف";
                continue;
            }

            $stream = fopen($source, 'rb');
            try {
                Storage::disk($entry['disk'])->writeStream($entry['relative_path'], $stream);
                $restored++;
            } catch (\Throwable $exception) {
                $errors[] = "{$entry['table']}.{$entry['relative_path']}: ".$exception->getMessage();
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        return ['restored' => $restored, 'skipped' => $skipped, 'errors' => $errors];
    }

    public function cleanup(string $extractDir): void
    {
        if (! is_dir($extractDir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($extractDir);
    }

    private function addFile(ZipArchive $zip, string $table, string $column, string $relativePath): array
    {
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');

        foreach (BackupAttachmentRegistry::CANDIDATE_DISKS as $disk) {
            if (Storage::disk($disk)->exists($normalized)) {
                $zip->addFile(Storage::disk($disk)->path($normalized), "files/{$disk}/{$normalized}");

                return ['table' => $table, 'column' => $column, 'disk' => $disk, 'relative_path' => $normalized, 'bundled' => true];
            }
        }

        return ['table' => $table, 'column' => $column, 'disk' => null, 'relative_path' => $normalized, 'bundled' => false];
    }
}
