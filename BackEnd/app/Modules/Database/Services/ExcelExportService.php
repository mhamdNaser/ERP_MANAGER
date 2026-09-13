<?php

namespace App\Modules\Database\Services;

use App\Modules\Database\Repositories\Interfaces\DatabaseRepositoryInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generic "export any table to Excel" — column headers come straight from
 * the table's own schema, so this needs no per-table configuration and
 * automatically covers new tables as they appear (mirrors how
 * DatabaseTableRegistry discovers tables dynamically).
 */
class ExcelExportService
{
    /** Hard safety cap: a runaway export can never hang the request or exhaust memory. */
    public const MAX_ROWS = 20000;

    public function __construct(
        private DatabaseTableRegistry $tables,
        private DatabaseRepositoryInterface $database,
    ) {}

    public function export(string $table): StreamedResponse
    {
        $allowed = $this->tables->backupTables();
        abort_unless(in_array($table, $allowed, true), 422, "جدول غير معروف: {$table}");

        $columns = $this->database->columnListing($table);
        abort_if($columns === [], 404, "الجدول {$table} غير موجود.");

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($table, 0, 31));
        $sheet->setRightToLeft(true);

        foreach (array_values($columns) as $index => $column) {
            $sheet->setCellValue([$index + 1, 1], $column);
        }

        $rowNumber = 1;
        $truncated = false;
        $this->database->chunkRows($table, $columns[0], 1000, function ($rows) use ($sheet, $columns, &$rowNumber, &$truncated) {
            foreach ($rows as $row) {
                if ($rowNumber >= self::MAX_ROWS) {
                    $truncated = true;

                    return false;
                }
                $rowNumber++;
                foreach (array_values($columns) as $index => $column) {
                    $sheet->setCellValue([$index + 1, $rowNumber], $this->cellValue($row->{$column} ?? null));
                }
            }

            return true;
        });

        $lastColumn = $sheet->getHighestColumn();
        $headerRange = "A1:{$lastColumn}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7EEEC');
        $sheet->getStyle("A1:{$lastColumn}{$rowNumber}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        foreach (range(1, count($columns)) as $index) {
            $sheet->getColumnDimensionByColumn($index)->setAutoSize(true);
        }

        if ($truncated) {
            $sheet->setCellValue([1, $rowNumber + 2], "تنبيه: تم الاقتصار على أول ".self::MAX_ROWS." صف من الجدول.");
        }

        $fileName = "{$table}-".now()->format('Y-m-d').'.xlsx';
        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    private function cellValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        return (string) ($value ?? '');
    }
}
