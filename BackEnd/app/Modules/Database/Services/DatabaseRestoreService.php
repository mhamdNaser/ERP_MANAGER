<?php

namespace App\Modules\Database\Services;

use App\Modules\Database\Repositories\Interfaces\DatabaseRepositoryInterface;
use Throwable;

class DatabaseRestoreService
{
    public function __construct(
        private DatabaseTableRegistry $tables,
        private DatabaseBackupService $backups,
        private PgDumpService $pgDump,
        private BackupFileBundler $fileBundler,
        private DatabaseRepositoryInterface $database,
    ) {}

    /**
     * يفرّغ الجداول المختارة ثم يعيد تحميل بياناتها من نسخة سابقة (JSON أو SQL
     * أو Backup)، ضمن معاملة واحدة. إن كانت النسخة محزَّمة (.zip، تضم ملفات
     * مرفقة)، تُستعاد الملفات كطبقة إضافية بعد نجاح استعادة الجداول — فشل
     * استعادة ملف واحد لا يُرجع استعادة الجداول التي التزمت أصلاً.
     *
     * @return array{tables: array, files: ?array}
     */
    public function restore(string $fileName, ?array $tables, bool $restoreFiles = true): array
    {
        $format = $this->backups->formatOfFile($fileName);
        abort_unless($format !== null, 404);

        $bundled = $this->backups->isBundled($fileName);
        $extraction = $bundled ? $this->fileBundler->extract($this->backups->absolutePath($fileName)) : null;

        try {
            $dumpPath = $extraction ? "{$extraction['dir']}/data.{$format}" : null;

            $summary = $format === 'json'
                ? $this->restoreJson($fileName, $tables ?? [])
                : $this->restoreDump($fileName, $format, $tables, $dumpPath);

            $filesSummary = null;
            if ($restoreFiles && $extraction) {
                $filesSummary = $this->fileBundler->restoreFiles($extraction['dir'], $extraction['manifest'], array_keys($summary));
            }

            return ['tables' => $summary, 'files' => $filesSummary];
        } finally {
            if ($extraction) {
                $this->fileBundler->cleanup($extraction['dir']);
            }
        }
    }

    private function restoreJson(string $fileName, array $tables): array
    {
        abort_if($tables === [], 422, 'لم يتم اختيار أي جدول.');
        $this->assertTruncatable($tables);

        $payload = $this->backups->payloadTables($fileName, $tables);
        $connection = $this->tables->maintenanceConnection();

        $summary = [];
        $this->guarded(function () use ($tables, $payload, $connection, &$summary) {
            $this->database->transaction(function () use ($tables, $payload, $connection, &$summary) {
                $this->clearRows($tables, $connection);

                foreach ($tables as $table) {
                    $rows = $payload[$table] ?? [];
                    foreach (array_chunk($rows, 500) as $chunk) {
                        if ($chunk !== []) {
                            $this->database->insertRows($table, $chunk, $connection);
                        }
                    }
                    $summary[$table] = count($rows);
                }
            }, $connection);
        }, 'تعذّرت الاستعادة');

        return $summary;
    }

    /**
     * صيغة SQL نسخة "بيانات فقط" تُعاد بالكامل كما هي (لا يمكن انتقاء جدول
     * واحد من ملف نصي بأمان)، أما Backup فتدعم استعادة انتقائية حقيقية عبر
     * pg_restore --data-only -t.
     */
    private function restoreDump(string $fileName, string $format, ?array $tables, ?string $extractedDumpPath): array
    {
        $recorded = $this->backups->dumpTablesFor($fileName);
        $target = $tables ?: ($recorded ?? $this->tables->truncatableTables());
        $this->assertTruncatable($target);

        if ($format === 'sql') {
            abort_if(
                $recorded !== null && array_diff($target, $recorded) !== [],
                422,
                'نسخة SQL نصية تُستعاد بكامل جداولها المسجَّلة فقط، لا يمكن انتقاء جزء منها.'
            );
        }

        // نسخة محزَّمة (.zip): pg_restore/psql عمليتان خارجيتان تحتاجان مسار
        // ملف dump حقيقي على القرص، وليس محتوى داخل أرشيف — لذا يُستخدم مسار
        // الملف المستخرَج مسبقًا بدل ملف الـ .zip نفسه.
        $absolutePath = $extractedDumpPath ?? $this->backups->absolutePath($fileName);
        $connection = $this->tables->maintenanceConnection();

        // تفريغ ثم استعادة كخطوتين منفصلتين عمدًا، لا معاملة واحدة: أداة
        // psql/pg_restore الخارجية تفتح اتصالها الخاص بقاعدة البيانات، فلو
        // بقيت معاملة الحذف مفتوحة من جهة PHP لحظة استدعائها لعلِقت إلى
        // الأبد بانتظار قفل يحرّره اتصال PHP — وهو لا يتحرر إلا بعد أن تنتهي
        // هي نفسها من الانتظار. أُغلقت هذه الحلقة بفصل الخطوتين.
        $this->guarded(function () use ($target, $connection) {
            $this->clearRows($target, $connection);
        }, 'تعذّر تفريغ الجداول قبل الاستعادة');

        $this->guarded(function () use ($absolutePath, $format, $target, $connection) {
            $this->pgDump->restore($absolutePath, $format, $target, $connection);
        }, 'تعذّرت الاستعادة');

        return array_fill_keys($target, null);
    }

    /**
     * يحذف صفوف الجداول المطلوبة تمهيدًا لإعادة التحميل — DELETE لا TRUNCATE
     * عمدًا: DELETE يحترم قاعدة ON DELETE الفعلية لكل مفتاح أجنبي يشير إلى
     * الجدول (مثل users.office_id التي تُصفَّر بلا حذف السجل المرجعي)، بخلاف
     * TRUNCATE التي تفرغ أي جدول مرجعي بالكامل مهما كانت قاعدته — وهذا بالضبط
     * ما تسبب سابقًا بمسح جدول users بالكامل عند استعادة نسخة من offices.
     */
    private function clearRows(array $tables, string $connection): void
    {
        foreach ($tables as $table) {
            $this->database->deleteRows($table, $connection);
        }
    }

    private function assertTruncatable(array $tables): void
    {
        $allowed = $this->tables->truncatableTables();
        $invalid = array_diff($tables, $allowed);
        abort_if($invalid !== [], 422, 'لا يمكن استعادة الجدول/الجداول التالية: ' . implode(', ', $invalid));
    }

    private function guarded(callable $callback, string $failureMessage): void
    {
        try {
            $callback();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            abort(500, "{$failureMessage}: " . $exception->getMessage());
        }
    }
}
