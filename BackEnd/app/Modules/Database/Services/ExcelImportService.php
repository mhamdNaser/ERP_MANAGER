<?php

namespace App\Modules\Database\Services;

use App\Modules\Database\Repositories\Interfaces\DatabaseRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Generic "import an Excel file into any (allowed) table" — columns come
 * straight from the target table's own schema (via ExcelRowCaster), not a
 * per-table alias contract like EmployeeImportService, since this must work
 * for arbitrary tables. Both preview() and commit() re-parse the uploaded
 * file from scratch (no server-side token/tmp-file between the two calls,
 * mirroring the app's existing Employee-import pattern) — the caller is
 * expected to re-submit the same file for commit after reviewing the
 * preview. commit() re-validates the table allow-list and re-runs every
 * check preview() ran; it refuses to insert anything if a single row still
 * has an error, rather than silently skipping bad rows in a table nobody
 * has reviewed a partial-import UX for yet.
 */
class ExcelImportService
{
    private const MAX_ROWS = 5000;

    public function __construct(
        private DatabaseTableRegistry $tables,
        private ExcelRowCaster $caster,
        private DatabaseRepositoryInterface $database,
    ) {}

    /** @return array{columns: array, rows: array, valid_count: int, error_count: int} */
    public function preview(string $table, UploadedFile $file): array
    {
        [$columns, $rows] = $this->parse($table, $file);

        return [
            'columns' => array_column($columns, 'name'),
            'rows' => array_map(fn (array $row) => [
                'row' => $row['row'],
                'values' => $row['preview'],
                'errors' => $row['errors'],
            ], $rows),
            'valid_count' => count(array_filter($rows, fn (array $row) => $row['errors'] === [])),
            'error_count' => count(array_filter($rows, fn (array $row) => $row['errors'] !== [])),
        ];
    }

    /** @return array{table: string, inserted: int} */
    public function commit(string $table, UploadedFile $file): array
    {
        [, $rows] = $this->parse($table, $file);

        $invalid = array_filter($rows, fn (array $row) => $row['errors'] !== []);
        abort_if($invalid !== [], 422, 'الملف يحوي صفوفًا غير صالحة؛ راجع المعاينة وصحّحها قبل الاستيراد.');
        abort_if($rows === [], 422, 'لا توجد صفوف صالحة للاستيراد.');

        $this->database->transaction(function () use ($table, $rows) {
            foreach (array_chunk(array_column($rows, 'payload'), 500) as $chunk) {
                $this->database->insertRows($table, $chunk);
            }
        });

        return ['table' => $table, 'inserted' => count($rows)];
    }

    /** @return array{0: array, 1: array} [importableColumns, parsedRows] */
    private function parse(string $table, UploadedFile $file): array
    {
        abort_unless(in_array($table, $this->tables->importableTables(), true), 422, "الاستيراد غير متاح لهذا الجدول: {$table}");

        $columns = collect($this->database->columnDefinitions($table))
            ->reject(fn (array $column) => $column['auto_increment'])
            ->values()
            ->all();
        $byName = collect($columns)->keyBy(fn (array $c) => mb_strtolower($c['name']));

        [$headerMap, $dataRows] = $this->readSheet($file, $byName);

        $rows = [];
        $rowsSeen = 0;
        foreach ($dataRows as $index => $raw) {
            if ($this->isBlankRow($raw)) {
                continue;
            }
            if (++$rowsSeen > self::MAX_ROWS) {
                break;
            }

            $rowNumber = $index + 1;
            $payload = [];
            $preview = [];
            $errors = [];

            foreach ($headerMap as $header => $columnIndex) {
                $column = $byName[$header];
                [$value, $error] = $this->caster->cast($raw[$columnIndex] ?? null, $column);
                $preview[$column['name']] = $raw[$columnIndex] ?? null;
                if ($error) {
                    $errors[] = $error;
                } else {
                    $payload[$column['name']] = $value;
                }
            }

            $rows[] = ['row' => $rowNumber, 'payload' => $payload, 'preview' => $preview, 'errors' => $errors];
        }

        return [$columns, $rows];
    }

    /** @return array{0: array<string,int>, 1: iterable} [lowercased-header => column index, data rows] */
    private function readSheet(UploadedFile $file, \Illuminate\Support\Collection $byName): array
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'تعذّرت قراءة الملف؛ تأكد أنه ملف Excel صالح.']);
        }

        $rows = $spreadsheet->getSheet(0)->toArray(null, true, true, false);
        $headerRow = null;
        $headerIndex = null;
        foreach ($rows as $index => $row) {
            if (! $this->isBlankRow($row)) {
                $headerRow = $row;
                $headerIndex = $index;
                break;
            }
        }
        abort_if($headerRow === null, 422, 'الملف فارغ.');

        $headerMap = [];
        $unknown = [];
        foreach ($headerRow as $columnIndex => $label) {
            $normalized = mb_strtolower(trim((string) $label));
            if ($normalized === '') {
                continue;
            }
            if (! $byName->has($normalized)) {
                $unknown[] = $label;

                continue;
            }
            $headerMap[$normalized] = $columnIndex;
        }
        abort_if($unknown !== [], 422, 'أعمدة غير معروفة في الملف: '.implode(', ', $unknown));

        $required = $byName->reject(fn (array $c) => $c['nullable'] || $c['default'] !== null)
            ->keys()->diff(array_keys($headerMap));
        abort_if($required->isNotEmpty(), 422, 'أعمدة مطلوبة غائبة عن الملف: '.$required->implode(', '));

        return [$headerMap, array_slice($rows, $headerIndex + 1, null, true)];
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
