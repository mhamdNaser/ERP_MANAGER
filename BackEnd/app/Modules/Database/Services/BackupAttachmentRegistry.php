<?php

namespace App\Modules\Database\Services;

use App\Modules\Database\Repositories\Interfaces\DatabaseRepositoryInterface;

/**
 * Finds which backup-eligible tables have file-attachment columns, and on
 * which disk those files live — dynamically, by inspecting the schema
 * itself, the same way DatabaseTableRegistry discovers tables. A column is
 * treated as an attachment path when its name contains "path" (case
 * insensitive) — this convention already holds for every real attachment
 * column in the app (drive_files.path, *.attachment_path, ...), so a new
 * attachment-bearing table is picked up automatically without editing this
 * file.
 *
 * The disk a given path belongs to isn't tracked per-column (attachments live
 * on either the "local" or "public" disk depending on the feature that wrote
 * them) — BackupFileBundler resolves it per-file by checking both disks.
 */
class BackupAttachmentRegistry
{
    public const CANDIDATE_DISKS = ['local', 'public'];

    public function __construct(
        private DatabaseTableRegistry $tables,
        private DatabaseRepositoryInterface $database,
    ) {}

    /** @return array<string, array<int, string>> table => [path column, ...] */
    public function pathColumns(?string $connection = null): array
    {
        $scoped = $this->tables->backupTables($connection);
        $rows = $this->database->pathColumnRows($connection, $scoped);

        $map = [];
        foreach ($rows as $row) {
            $table = $row->table_name;
            if (! in_array($table, $scoped, true)) {
                continue;
            }
            $map[$table][] = $row->column_name;
        }

        return $map;
    }

}
