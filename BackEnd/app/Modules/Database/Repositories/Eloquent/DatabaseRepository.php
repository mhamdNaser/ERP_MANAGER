<?php
namespace App\Modules\Database\Repositories\Eloquent;

use App\Models\DatabaseMaintenanceLog;
use App\Modules\Database\Repositories\Interfaces\DatabaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseRepository implements DatabaseRepositoryInterface
{
    public function driver(?string $connection = null): string
    {
        return DB::connection($connection)->getDriverName();
    }

    public function defaultConnection(): string
    {
        return config('database.default');
    }

    public function connectionName(): string
    {
        return DB::connection()->getName();
    }

    public function databaseName(): string
    {
        return DB::connection()->getDatabaseName();
    }

    public function connectionHasPassword(string $connection): bool
    {
        return (bool) config("database.connections.{$connection}.password");
    }

    public function tableNames(?string $connection = null): array
    {
        $tables = match ($this->driver($connection)) {
            'pgsql' => collect(DB::connection($connection)->select(
                "select tablename from pg_tables where schemaname = 'public' order by tablename"
            ))->pluck('tablename'),
            'sqlite' => collect(DB::connection($connection)->select(
                "select name as tablename from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"
            ))->pluck('tablename'),
            'mysql' => collect(DB::connection($connection)->select(
                'select table_name as tablename from information_schema.tables where table_schema = database() order by table_name'
            ))->pluck('tablename'),
            default => collect(DB::connection($connection)->select(
                "select table_name as tablename from information_schema.tables where table_schema = current_schema() order by table_name"
            ))->pluck('tablename'),
        };

        return $tables->values()->all();
    }

    public function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    public function columnListing(string $table): array
    {
        return Schema::getColumnListing($table);
    }

    public function columnDefinitions(string $table): array
    {
        return Schema::getColumns($table);
    }

    public function pathColumnRows(?string $connection, array $scopedTables): array
    {
        return match ($this->driver($connection)) {
            'pgsql' => DB::connection($connection)->select(
                "select table_name, column_name from information_schema.columns
                 where table_schema = 'public' and column_name ilike '%path%'
                 order by table_name, column_name"
            ),
            'sqlite' => $this->sqlitePathColumns($connection, $scopedTables),
            default => DB::connection($connection)->select(
                "select table_name, column_name from information_schema.columns
                 where table_schema = current_schema() and lower(column_name) like '%path%'
                 order by table_name, column_name"
            ),
        };
    }

    public function allRows(string $table, array $filter = []): Collection
    {
        return $this->filtered(DB::table($table), $filter)->get();
    }

    /**
     * يقيّد الاستعلام بمرشِّح الحزمة الجاهزة.
     *
     * المرشِّح بسيط عمداً — مساواةٌ أو «ليس فارغاً» — لأن غرضه تمييز صفوف
     * كيانٍ داخل جدول مشترك (ملفات المهام داخل الدرايف)، لا بناء لغة استعلام.
     */
    private function filtered(Builder $query, array $filter): Builder
    {
        foreach ($filter['where'] ?? [] as $column => $value) {
            $query->where($column, $value);
        }

        foreach ($filter['where_not_null'] ?? [] as $column) {
            $query->whereNotNull($column);
        }

        return $query;
    }

    public function chunkRows(string $table, string $orderBy, int $size, callable $callback): void
    {
        DB::table($table)->orderBy($orderBy)->chunk($size, $callback);
    }

    public function columnValues(string $table, string $column, ?string $connection = null, array $filter = []): Collection
    {
        $query = DB::connection($connection)->table($table)->whereNotNull($column);

        return $this->filtered($query, $filter)->pluck($column);
    }

    public function insertRows(string $table, array $rows, ?string $connection = null): void
    {
        DB::connection($connection)->table($table)->insert($rows);
    }

    public function deleteRows(string $table, ?string $connection = null, array $filter = []): void
    {
        $this->filtered(DB::connection($connection)->table($table), $filter)->delete();
    }

    /**
     * أسماء الجداول تأتي حصراً من قائمة مكتشفة من القاعدة نفسها لا من مدخلات
     * المستخدم — الاقتباس هنا دفاع إضافي لا الحارس الوحيد ضد الحقن.
     * بلا RESTART IDENTITY عمداً: إعادة ضبط المسلسل تتطلب ملكية العمود في
     * postgres، بينما التفريغ العادي يكتفي بصلاحية TRUNCATE القابلة للمنح.
     * وبلا CASCADE عمداً أيضاً — وهذا أهم: TRUNCATE ... CASCADE يُفرغ أي جدول
     * آخر لديه مفتاح أجنبي يشير إلى الجداول المستهدفة بصرف النظر عن قاعدة
     * ON DELETE الخاصة به (حتى لو كانت SET NULL)، وهذا ما أدى فعلياً لمسح
     * جدول users بالكامل عند إفراغ offices وحده. بلا CASCADE يفشل التفريغ
     * بخطأ واضح بدل أن يمسح بصمت — وفشل التنفيذ هنا أأمن من نجاحه.
     */
    public function truncateTables(array $tables, ?string $connection = null): void
    {
        $quoted = collect($tables)->map(fn (string $table) => '"' . str_replace('"', '', $table) . '"')->implode(', ');
        DB::connection($connection)->statement("TRUNCATE TABLE {$quoted}");
    }

    public function clearSqliteSequence(string $table, ?string $connection = null): void
    {
        DB::connection($connection)->table('sqlite_sequence')->where('name', $table)->delete();
    }

    public function transaction(callable $callback, ?string $connection = null): mixed
    {
        return DB::connection($connection)->transaction($callback);
    }

    public function logMaintenance(array $attributes): void
    {
        DatabaseMaintenanceLog::create($attributes);
    }

    public function maintenanceLogs(int $limit): EloquentCollection
    {
        return DatabaseMaintenanceLog::with('user:id,name')->latest('created_at')->limit($limit)->get();
    }

    /** @return object[] صفوف بشكل {table_name, column_name} */
    private function sqlitePathColumns(?string $connection, array $scopedTables): array
    {
        $rows = [];
        foreach ($scopedTables as $table) {
            foreach (DB::connection($connection)->select("pragma table_info(\"{$table}\")") as $column) {
                if (str_contains(strtolower($column->name), 'path')) {
                    $rows[] = (object) ['table_name' => $table, 'column_name' => $column->name];
                }
            }
        }

        return $rows;
    }
}
