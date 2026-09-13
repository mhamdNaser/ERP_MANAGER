<?php
namespace App\Modules\Database\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * الوصول الخام إلى المخطط والصفوف. السياسات — أي جدول يُنسخ أو يُفرَّغ أو
 * يُستورَد، وأي اتصال تُنفَّذ عليه الصيانة — تبقى في خدمات الوحدة.
 */
interface DatabaseRepositoryInterface
{
    public function driver(?string $connection = null): string;
    public function defaultConnection(): string;
    public function connectionName(): string;
    public function databaseName(): string;
    public function connectionHasPassword(string $connection): bool;

    /** أسماء جداول القاعدة كما تُكتشف من المحرّك نفسه. */
    public function tableNames(?string $connection = null): array;
    public function hasTable(string $table): bool;
    public function columnListing(string $table): array;
    public function columnDefinitions(string $table): array;
    /** أعمدة مسارات المرفقات: صفوف {table_name, column_name}. */
    public function pathColumnRows(?string $connection, array $scopedTables): array;

    public function allRows(string $table): Collection;
    public function chunkRows(string $table, string $orderBy, int $size, callable $callback): void;
    public function columnValues(string $table, string $column, ?string $connection = null): Collection;
    public function insertRows(string $table, array $rows, ?string $connection = null): void;
    public function deleteRows(string $table, ?string $connection = null): void;
    public function truncateTables(array $tables, ?string $connection = null): void;
    public function clearSqliteSequence(string $table, ?string $connection = null): void;

    public function transaction(callable $callback, ?string $connection = null): mixed;

    public function logMaintenance(array $attributes): void;
    public function maintenanceLogs(int $limit): EloquentCollection;
}
