<?php

namespace App\Modules\Database\Services;

use App\Modules\Database\Repositories\Interfaces\DatabaseRepositoryInterface;

class DatabaseTableRegistry
{
    /** جداول بنية تحتية لا بيانات عمل — لا تدخل النسخ الاحتياطي ولا تُفرَّغ أبداً. */
    public const INFRA_TABLES = [
        'migrations', 'sessions', 'jobs', 'job_batches', 'failed_jobs',
        'cache', 'cache_locks', 'password_reset_tokens', 'personal_access_tokens',
        'database_maintenance_logs',
    ];

    /**
     * جداول المصادقة والصلاحيات: تُنسخ ضمن النسخ الاحتياطي (بيانات عمل حقيقية)
     * لكن يُمنع إفراغها أو الكتابة فوقها من هذه الميزة، لتفادي حرمان الجميع
     * من الدخول — بما فيهم مدير قواعد البيانات نفسه.
     */
    public const AUTH_TABLES = [
        'users', 'roles', 'permissions',
        'model_has_roles', 'model_has_permissions', 'role_has_permissions',
    ];

    public function __construct(private DatabaseRepositoryInterface $database) {}

    public function allTables(?string $connection = null): array
    {
        return $this->database->tableNames($connection);
    }

    public function backupTables(?string $connection = null): array
    {
        return collect($this->allTables($connection))
            ->reject(fn (string $table) => in_array($table, self::INFRA_TABLES, true))
            ->values()
            ->all();
    }

    public function truncatableTables(?string $connection = null): array
    {
        $blocked = [...self::INFRA_TABLES, ...self::AUTH_TABLES];

        return collect($this->allTables($connection))
            ->reject(fn (string $table) => in_array($table, $blocked, true))
            ->values()
            ->all();
    }

    /**
     * جداول مستبعدة صراحةً من ميزة استيراد الإكسل حتى تُثبت أمانها عليها —
     * حالياً tasks لأنه يحوي بيانات إنتاج حقيقية. تفعيل الاستيراد على جدول
     * لاحقاً هو حذفه من هذا الثابت فقط، لا حاجة لأي تعديل آخر.
     */
    public const IMPORT_BLOCKED_TABLES = ['tasks'];

    public function importableTables(?string $connection = null): array
    {
        return collect($this->backupTables($connection))
            ->reject(fn (string $table) => in_array($table, self::IMPORT_BLOCKED_TABLES, true))
            ->values()
            ->all();
    }

    /**
     * الاتصال المستخدم لعمليات الصيانة (إفراغ/استعادة): يحتاج صلاحيات مالك
     * الجدول لتنفيذ TRUNCATE، لذلك يُفضَّل pgsql_owner إن كانت بياناته متوفرة.
     */
    public function maintenanceConnection(): string
    {
        $default = $this->database->defaultConnection();
        if ($default !== 'pgsql') {
            return $default;
        }

        return $this->database->connectionHasPassword('pgsql_owner') ? 'pgsql_owner' : 'pgsql';
    }
}
