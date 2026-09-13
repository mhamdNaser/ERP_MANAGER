<?php

namespace App\Modules\Database\Services;

use App\Modules\Database\Repositories\Interfaces\DatabaseRepositoryInterface;
use Throwable;

class DatabaseTruncateService
{
    public function __construct(
        private DatabaseTableRegistry $tables,
        private DatabaseRepositoryInterface $database,
    ) {}

    public function truncateAll(): array
    {
        return $this->truncate($this->tables->truncatableTables());
    }

    public function truncate(array $tables): array
    {
        abort_if($tables === [], 422, 'لم يتم اختيار أي جدول.');

        $allowed = $this->tables->truncatableTables();
        $invalid = array_diff($tables, $allowed);
        abort_if($invalid !== [], 422, 'لا يمكن إفراغ الجدول/الجداول التالية: ' . implode(', ', $invalid));

        $connection = $this->tables->maintenanceConnection();
        $driver = $this->database->driver($connection);

        try {
            $this->database->transaction(function () use ($tables, $connection, $driver) {
                if ($driver === 'pgsql') {
                    $this->database->truncateTables($tables, $connection);

                    return;
                }

                foreach ($tables as $table) {
                    $this->database->deleteRows($table, $connection);
                    if ($driver === 'sqlite') {
                        $this->database->clearSqliteSequence($table, $connection);
                    }
                }
            }, $connection);
        } catch (Throwable $exception) {
            abort(500, 'تعذّر إفراغ الجداول: ' . $exception->getMessage());
        }

        return $tables;
    }
}
