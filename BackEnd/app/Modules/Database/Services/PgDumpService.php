<?php

namespace App\Modules\Database\Services;

use Symfony\Component\Process\Process;

/**
 * ينشئ نسخة SQL نصية (بيانات فقط، جمل INSERT قابلة للتنفيذ مباشرة) أو نسخة
 * pg_dump الثنائية الكاملة (-F c)، ويستعيد منهما — عبر تشغيل أدوات postgres
 * الحقيقية (pg_dump / psql / pg_restore) كعمليات خارجية، على نمط
 * DocxPdfConverter في وحدة Communications.
 *
 * الاستعادة تعتمد "بيانات فقط" عمداً (بلا DROP/CREATE) لأن حساب التطبيق
 * (cnd_owner) يملك صلاحيات DML ممنوحة على الجداول دون أن يكون مالكها —
 * migrations تُشغَّل بحساب آخر (cnd_app) هو المالك الفعلي. تفريغ الجدول ثم
 * إعادة تحميل بياناته فقط يعمل بصلاحيات المنح العادية، بخلاف حذف/إعادة إنشاء
 * الجدول التي تتطلب الملكية.
 */
class PgDumpService
{
    public function dump(string $absolutePath, string $format, ?array $tables, string $connection): void
    {
        $config = $this->pgsqlConfig($connection);

        $arguments = [
            $this->resolveBinary('pg_dump'),
            '-h', $config['host'],
            '-p', (string) $config['port'],
            '-U', $config['username'],
            '-d', $config['database'],
            '-F', $format === 'backup' ? 'c' : 'p',
            '-f', $absolutePath,
            '--no-owner',
            '--no-privileges',
        ];

        if ($format === 'sql') {
            // نصية = بيانات فقط قابلة لإعادة التشغيل داخل مخطط موجود مسبقًا
            // (بلا CREATE TABLE)، على عكس صيغة backup التي تبقى نسخة بنيوية
            // كاملة تصلح أيضًا للاستخدام الخارجي عبر pg_restore يدويًا.
            $arguments[] = '--data-only';
            $arguments[] = '--column-inserts';
        }

        foreach ($tables ?? [] as $table) {
            $arguments[] = '-t';
            $arguments[] = $table;
        }

        $this->run($arguments, $config, 'فشل إنشاء نسخة SQL/Backup');
    }

    /** يفرّغ الجداول المطلوبة عبر DatabaseTruncateService أولاً، ثم يستدعي هذا لإعادة تحميل بياناتها فقط. */
    public function restore(string $absolutePath, string $format, ?array $tables, string $connection): void
    {
        $config = $this->pgsqlConfig($connection);

        if ($format === 'sql') {
            $arguments = [
                $this->resolveBinary('psql'),
                '-h', $config['host'],
                '-p', (string) $config['port'],
                '-U', $config['username'],
                '-d', $config['database'],
                '-v', 'ON_ERROR_STOP=1',
                '--single-transaction',
                '-f', $absolutePath,
            ];
        } else {
            $arguments = [
                $this->resolveBinary('pg_restore'),
                '-h', $config['host'],
                '-p', (string) $config['port'],
                '-U', $config['username'],
                '-d', $config['database'],
                '--data-only',
                '--no-owner',
                '--no-privileges',
                '--single-transaction',
            ];
            foreach ($tables ?? [] as $table) {
                $arguments[] = '-t';
                $arguments[] = $table;
            }
            $arguments[] = $absolutePath;
        }

        $this->run($arguments, $config, 'فشلت الاستعادة من ملف SQL/Backup');
    }

    private function pgsqlConfig(string $connection): array
    {
        $config = config("database.connections.{$connection}");
        abort_unless(($config['driver'] ?? null) === 'pgsql', 422, 'تصدير/استعادة SQL أو Backup متاحة فقط مع اتصال PostgreSQL.');

        return $config;
    }

    private function run(array $arguments, array $config, string $failureMessage): void
    {
        $process = new Process($arguments, null, ['PGPASSWORD' => (string) ($config['password'] ?? '')]);
        // بلا هذا، psql/pg_restore يتركان stdin مفتوحًا بلا تلقيم فيعلقان إلى
        // الأبد بانتظار إدخال لن يصل أبدًا — pg_dump لا يقرأ stdin فلا يتأثر.
        $process->setInput('');
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'سبب غير معروف.';
            abort(500, "{$failureMessage}: {$error}");
        }
    }

    private function resolveBinary(string $name): string
    {
        $configured = env(strtoupper($name) . '_BINARY');
        if ($configured && is_file($configured)) {
            return $configured;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = glob('C:\\Program Files\\PostgreSQL\\*\\bin\\' . $name . '.exe') ?: [];
            rsort($candidates);
            if ($candidates !== []) {
                return $candidates[0];
            }
        }

        return $name;
    }
}
