<?php

namespace App\Modules\Maintenance\Services;

use App\Models\MaintenanceItem;
use App\Modules\Maintenance\Repositories\Interfaces\MaintenanceRepositoryInterface;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تصدير مستودع الصيانة إلى Excel بترويسة ملفات الفرع نفسها. أعمدة الحالات
 * تحمل أسماءها العربية، فيعود الملف إلى التبويب برفعه كما هو.
 */
class MaintenanceExportService
{
    private const HEADER_ROWS = [
        'الجمهورية العربية السورية | Syrian Arab Republic',
        'وزارة الداخلية | Ministry of interior',
        'إدارة الاتصالات و الشبكات - فرع الصيانة',
    ];

    private const FOREST = '06312D';

    private const LIGHT = 'F4F9F7';

    /** التوقيت المعروض في الملف: توقيت الفرع لا توقيت الخادم (UTC). */
    private const DISPLAY_TIMEZONE = 'Asia/Damascus';

    public function __construct(
        private MaintenanceRepositoryInterface $maintenance,
        private MaintenanceStatisticsService $statistics,
    ) {}

    public function export(array $filters): StreamedResponse
    {
        $items = $this->maintenance->items($filters);
        $spreadsheet = new Spreadsheet();

        $this->itemsSheet($spreadsheet->getActiveSheet(), $items);
        $this->summarySheet($spreadsheet->createSheet(), $items);
        $this->movementsSheet($spreadsheet->createSheet());
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $fileName = 'maintenance-'.now()->format('Y-m-d').'.xlsx';

        return new StreamedResponse(fn () => $writer->save('php://output'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'no-store',
        ]);
    }

    private function itemsSheet(Worksheet $sheet, Collection $items): void
    {
        $sheet->setTitle('القطع');
        $headers = [
            'الكود', 'اسم العنصر', 'رقم القطعة', 'الفئة', 'النوع', 'الماركة', 'الجهاز', 'الوحدة', 'مكان التخزين', 'سعر الوحدة ($)',
            ...array_map(fn ($status) => MaintenanceWorkflow::label($status), MaintenanceWorkflow::STATUSES),
            'العدد الكلي', 'القيمة ($)', 'الحد الأدنى', 'ملاحظات',
        ];
        $row = $this->heading($sheet, 'سجل قطع الصيانة', count($headers));
        $headerRow = $row;
        $this->put($sheet, $headers, "A{$row}");

        foreach ($items as $item) {
            $row++;
            $this->put($sheet, [
                $item->code, $item->name, $item->part_number, $item->category?->name, $item->type?->name, $item->brand?->name,
                $item->device, $item->unit, $item->location, $item->unit_price,
                ...array_values($item->quantities),
                $item->total_quantity,
                round($item->total_quantity * ($item->unit_price ?? 0), 2),
                $item->min_quantity ?: null,
                $item->notes,
            ], "A{$row}");
            if ($item->is_low_stock) {
                $sheet->getStyle("A{$row}:".$this->column(count($headers))."{$row}")->getFont()->getColor()->setRGB('B42318');
            }
        }

        $row++;
        $firstStatus = 11;
        $sheet->setCellValue("B{$row}", 'الإجمالي');
        foreach (range($firstStatus, $firstStatus + count(MaintenanceWorkflow::STATUSES) + 1) as $column) {
            $letter = $this->column($column);
            $sheet->setCellValue("{$letter}{$row}", $items->isEmpty() ? 0 : '=SUM('.$letter.($headerRow + 1).":{$letter}".($row - 1).')');
        }
        $sheet->getStyle("A{$row}:".$this->column(count($headers))."{$row}")->getFont()->setBold(true);

        $this->table($sheet, $headerRow, $row, count($headers));
        $this->signature($sheet, $row + 2, count($headers));
    }

    private function summarySheet(Worksheet $sheet, Collection $items): void
    {
        $sheet->setTitle('الإحصاءات');
        $row = $this->heading($sheet, 'توزع القطع على الحالات', 4);
        $headerRow = $row;
        $this->put($sheet, ['الحالة', 'عدد القطع', 'عدد الأصناف', 'القيمة ($)'], "A{$row}");
        foreach (MaintenanceWorkflow::STATUSES as $status) {
            $row++;
            $this->put($sheet, [
                MaintenanceWorkflow::label($status),
                $items->sum(fn (MaintenanceItem $item) => $item->quantityIn($status)),
                $items->filter(fn (MaintenanceItem $item) => $item->quantityIn($status) > 0)->count(),
                round($items->sum(fn (MaintenanceItem $item) => $item->quantityIn($status) * ($item->unit_price ?? 0)), 2),
            ], "A{$row}");
        }
        $this->table($sheet, $headerRow, $row, 4);

        $row += 2;
        $headerRow = $row;
        $this->put($sheet, ['الفئة', 'عدد الأصناف', 'عدد القطع', 'القيمة ($)'], "A{$row}");
        foreach ($items->groupBy(fn ($item) => $item->category?->name ?? 'بلا فئة') as $name => $group) {
            $row++;
            $this->put($sheet, [
                $name,
                $group->count(),
                $group->sum('total_quantity'),
                round($group->sum(fn (MaintenanceItem $item) => $item->total_quantity * ($item->unit_price ?? 0)), 2),
            ], "A{$row}");
        }
        $this->table($sheet, $headerRow, $row, 4);
    }

    private function movementsSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('سجل الحركات');
        $row = $this->heading($sheet, 'آخر الحركات على القطع', 9);
        $headerRow = $row;
        $this->put($sheet, ['التاريخ', 'الكود', 'القطعة', 'الحركة', 'من', 'إلى', 'العدد', 'بواسطة', 'ملاحظة'], "A{$row}");
        foreach ($this->maintenance->recentMovements(1000) as $movement) {
            $row++;
            $this->put($sheet, [
                $movement->created_at?->copy()->timezone(self::DISPLAY_TIMEZONE)->format('Y-m-d H:i'),
                $movement->item?->code,
                trim(($movement->item?->name ?? '').' '.($movement->item?->part_number ?? '')),
                MaintenanceWorkflow::KIND_LABELS[$movement->kind] ?? $movement->kind,
                $movement->from_status ? MaintenanceWorkflow::label($movement->from_status) : 'خارج المستودع',
                $movement->to_status ? MaintenanceWorkflow::label($movement->to_status) : 'خارج القسم',
                $movement->quantity,
                $movement->actor?->name,
                $movement->note,
            ], "A{$row}");
        }
        $this->table($sheet, $headerRow, $row, 9);
    }

    /** ترويسة الفرع ثم العنوان؛ يعيد رقم السطر الذي تبدأ عنده العناوين. */
    private function heading(Worksheet $sheet, string $title, int $columns): int
    {
        $sheet->setRightToLeft(true);
        $last = $this->column($columns);
        foreach ([...self::HEADER_ROWS, $title.' — '.now()->format('Y-m-d')] as $index => $text) {
            $row = $index + 1;
            $sheet->mergeCells("A{$row}:{$last}{$row}");
            $sheet->setCellValue("A{$row}", $text);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize($index === 3 ? 14 : 11);
        }
        $sheet->getStyle('A4')->getFont()->getColor()->setRGB(self::FOREST);

        return count(self::HEADER_ROWS) + 3;
    }

    private function table(Worksheet $sheet, int $headerRow, int $lastRow, int $columns): void
    {
        $last = $this->column($columns);
        $header = $sheet->getStyle("A{$headerRow}:{$last}{$headerRow}");
        $header->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::FOREST);
        $header->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

        for ($row = $headerRow + 1; $row <= $lastRow; $row += 2) {
            $sheet->getStyle("A{$row}:{$last}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::LIGHT);
        }
        $sheet->getStyle("A{$headerRow}:{$last}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CFDED9');
        $sheet->getStyle("A{$headerRow}:{$last}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        foreach (range(1, $columns) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }
        $sheet->freezePane('A'.($headerRow + 1));
    }

    private function signature(Worksheet $sheet, int $row, int $columns): void
    {
        $letter = $this->column(max(1, $columns - 3));
        $sheet->setCellValue("{$letter}{$row}", 'مسؤول فرع الصيانة');
        $sheet->getStyle("{$letter}{$row}")->getFont()->setBold(true);
    }

    /**
     * fromArray يتجاوز كل قيمة تساوي «القيمة الفارغة» بمقارنة غير صارمة،
     * و0 == null في PHP، فتضيع الأصفار. قيمة فارغة لا تطابق أي رقم تُبقيها.
     */
    private function put(Worksheet $sheet, array $values, string $start): void
    {
        $sheet->fromArray($values, '__keep_empty__', $start);
    }

    private function column(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
    }
}
