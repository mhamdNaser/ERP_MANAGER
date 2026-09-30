<?php

namespace App\Modules\Maintenance\Services;

use App\Models\MaintenanceImport;
use App\Models\User;
use App\Modules\Maintenance\Repositories\Interfaces\MaintenanceRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Stringable;
use Throwable;

/**
 * يقرأ ملفات Excel التي يعمل بها فرع الصيانة كما هي، دون قالب مفروض.
 *
 * ملفات الفرع تبدأ بأسطر ترويسة (الجمهورية، الوزارة، الإدارة) ثم سطر عناوين،
 * وقد يتكرر الجدول بترويسته في الورقة نفسها، وتنتهي الجداول بأسطر «إجمالي».
 * لذلك يُبحث عن سطر العناوين في كل موضع، وتُطابَق الأعمدة بأسمائها لا بترتيبها،
 * ويُتجاوز كل سطر لا يحمل بيانات قطعة. ملف التصدير من هذا التبويب يُقرأ أيضاً،
 * فأعمدة الحالات فيه تعود كل كمية إلى حالتها.
 */
class MaintenanceImportService
{
    public const MAX_ROWS = 3000;

    /** أسماء الأعمدة كما تَرِد في ملفات الفرع وفي ملف التصدير. */
    private const HEADERS = [
        'name' => ['اسم العنصر', 'اسم الأداة', 'اسم الاداة', 'اسم القطعة', 'اسم الصنف', 'الاسم', 'الصنف', 'العنصر', 'name', 'item'],
        'part_number' => ['سيريال العنصر', 'السيريال', 'سيريال', 'الرقم التسلسلي', 'رقم القطعة', 'رقم الموديل', 'الموديل', 'part number', 'serial'],
        'device' => ['الجهاز', 'الجهاز المستهدف', 'device'],
        'unit' => ['الوحدة', 'unit'],
        // «النوع» في ملف العدد يعني الوحدة (قطعة، بكرة...)، وفي ملف التصدير نوع القطعة.
        'type_or_unit' => ['النوع'],
        'category' => ['الفئة', 'التصنيف', 'category'],
        'brand' => ['الماركة', 'البراند', 'brand'],
        'notes' => ['ملاحظات', 'الملاحظات', 'notes'],
        'unit_price' => ['سعر الوحدة', 'السعر', 'unit price', 'price'],
        'quantity' => ['العدد', 'الكمية', 'quantity', 'qty'],
        'location' => ['مكان التخزين', 'الموقع', 'المكان', 'location'],
        'min_quantity' => ['الحد الأدنى', 'حد الطلب'],
        'code' => ['الكود', 'الرمز', 'code'],
        'image' => ['صورة القطعة', 'الصورة', 'صورة', 'image', 'photo'],
    ];

    private const UNIT_WORDS = ['قطعة', 'قطع', 'بكرة', 'علبة', 'طقم', 'متر', 'لتر', 'كيلو', 'غرام', 'رول', 'عبوة', 'زوج', 'صندوق', 'كرتونة', 'pcs', 'piece', 'set', 'box'];

    private const TOTAL_PREFIXES = ['الإجمالي', 'إجمالي', 'اجمالي', 'الاجمالي', 'المجموع', 'total'];

    /** كلمات تلي «ماركة» في الملاحظات ولا تكون اسم ماركة. */
    private const NOT_BRANDS = ['ممتازة', 'جيدة', 'جيد', 'إن', 'ان', 'حسب', 'أصلية', 'اصلية'];

    /** صور القطع المضمَّنة في خلايا الملف، بمفتاح «الورقة:السطر». */
    private array $images = [];

    public function __construct(
        private MaintenanceRepositoryInterface $maintenance,
        private MaintenanceInventoryService $inventory,
    ) {}

    /** يقرأ الملف ويصنّف كل سطر دون أن يكتب شيئاً. */
    public function preview(UploadedFile $file, ?int $categoryId, string $existing = 'skip'): array
    {
        $rows = $this->parse($file);
        $typeNames = [];

        foreach ($rows as &$row) {
            // عمود «الفئة» في الملف يغلب الفئة المختارة عند الرفع.
            $rowCategoryId = $row['category'] ? $this->maintenance->categoryIdNamed($row['category']) : $categoryId;
            if (! $row['type'] && $rowCategoryId) {
                $typeNames[$rowCategoryId] ??= $this->maintenance->typeNamesOf($rowCategoryId);
                $row['type'] = $this->guessType($row['name'], $typeNames[$rowCategoryId]);
            }

            // التكرار يُقاس على ما سُجّل قبل هذا الملف فقط: السطران المتشابهان
            // داخل الملف نفسه قطعتان مختلفتان في ملفات الفرع (سعر وملاحظة مختلفان).
            $match = ($rowCategoryId || ! $row['category'])
                ? $this->maintenance->findMatchingItem($row['name'], $row['part_number'], $rowCategoryId)
                : null;
            $row['action'] = $match ? ($existing === 'add' ? 'merge' : 'skip') : 'create';
            $row['existing_code'] = $match?->code;
        }
        unset($row);

        return [
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'create' => collect($rows)->where('action', 'create')->count(),
                'merge' => collect($rows)->where('action', 'merge')->count(),
                'skip' => collect($rows)->where('action', 'skip')->count(),
                'units' => collect($rows)->where('action', '!=', 'skip')->sum(fn ($row) => array_sum($row['quantities'])),
                'value' => round(collect($rows)->where('action', '!=', 'skip')->sum(fn ($row) => array_sum($row['quantities']) * ($row['unit_price'] ?? 0)), 2),
            ],
        ];
    }

    /** يكتب الأسطر: الجديد صنفاً بكمياته، والموجود يُتجاوز أو تُضاف كميته إدخالاً. */
    public function commit(UploadedFile $file, ?int $categoryId, string $existing, User $actor): MaintenanceImport
    {
        $preview = $this->preview($file, $categoryId, $existing);
        $fileName = $file->getClientOriginalName();
        $path = $file->store('maintenance-imports', 'public');

        return $this->maintenance->transaction(function () use ($preview, $categoryId, $existing, $actor, $fileName, $path) {
            $import = $this->maintenance->createImport([
                'file_name' => $fileName,
                'file_path' => $path,
                'category_id' => $categoryId,
                'actor_id' => $actor->id,
            ]);
            $counts = ['created' => 0, 'merged' => 0, 'skipped' => 0];
            $note = "استيراد من الملف «{$fileName}»";

            foreach ($preview['rows'] as $row) {
                $rowCategoryId = $row['category']
                    ? $this->maintenance->categoryNamed($row['category'])->id
                    : $categoryId;
                $item = $this->maintenance->findMatchingItem($row['name'], $row['part_number'], $rowCategoryId, exceptImportId: $import->id);

                if ($item && $existing !== 'add') {
                    $counts['skipped']++;
                    continue;
                }

                if (! $item) {
                    $item = $this->maintenance->createItem([
                        'name' => $row['name'],
                        'part_number' => $row['part_number'],
                        'category_id' => $rowCategoryId,
                        'type_id' => $row['type'] && $rowCategoryId ? $this->maintenance->typeNamed($rowCategoryId, $row['type'])->id : null,
                        'brand_id' => $row['brand'] ? $this->maintenance->brandNamed($row['brand'])->id : null,
                        'device' => $row['device'],
                        'unit' => $row['unit'] ?: 'قطعة',
                        'unit_price' => $row['unit_price'],
                        'min_quantity' => $row['min_quantity'] ?? 0,
                        'location' => $row['location'],
                        'notes' => $row['notes'],
                        'image_path' => $this->storeImage($row['image_key']),
                        'import_id' => $import->id,
                        'created_by_id' => $actor->id,
                    ]);
                    $counts['created']++;
                } else {
                    $counts['merged']++;
                }

                foreach ($row['quantities'] as $status => $quantity) {
                    if ($quantity > 0) {
                        $this->inventory->receive($item, $quantity, $actor, $note, $status);
                    }
                }
            }

            $import->update([
                'created_count' => $counts['created'],
                'merged_count' => $counts['merged'],
                'skipped_count' => $counts['skipped'],
            ]);

            return $import->fresh(['category:id,name', 'actor:id,name']);
        });
    }

    /** @return array<int, array> أسطر القطع بعد تنظيفها، بترتيب ورودها. */
    private function parse(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (Throwable) {
            abort(422, 'تعذّرت قراءة الملف. تأكد أنه ملف Excel سليم (xlsx أو xls أو csv).');
        }

        $rows = [];
        $this->images = [];
        foreach ($spreadsheet->getWorksheetIterator() as $sheetIndex => $sheet) {
            $map = null;
            // الصور الطافية فوق الخلايا تُعرَف بخلية مرساتها.
            $anchored = collect($sheet->getDrawingCollection())
                ->filter(fn ($drawing) => $drawing instanceof Drawing)
                ->keyBy(fn (Drawing $drawing) => $drawing->getCoordinates());

            foreach ($sheet->toArray(null, true, false, false) as $index => $cells) {
                $headerMap = $this->headerMap($cells);
                if ($headerMap) {
                    // جدول جديد في الورقة نفسها يبدأ بترويسته.
                    $map = $headerMap;
                    continue;
                }
                if (! $map) {
                    continue;
                }

                $row = $this->row($cells, $map);
                if ($row) {
                    $key = "{$sheetIndex}:".($index + 1);
                    $image = $this->imageAt($cells, $map, $anchored, $index + 1);
                    if ($image) {
                        $this->images[$key] = $image;
                    }
                    $rows[] = ['sheet' => $sheet->getTitle(), 'row' => $index + 1, ...$row, 'image_key' => $image ? $key : null, 'has_image' => (bool) $image];
                }
                abort_if(count($rows) > self::MAX_ROWS, 422, 'الملف أكبر من الحد المسموح ('.self::MAX_ROWS.' سطر).');
            }
        }

        abort_if($rows === [], 422, 'لم يُعثر في الملف على جدول قطع. يجب أن يحوي سطر عناوين فيه عمود «اسم العنصر» أو «اسم الأداة» أو «الاسم».');

        return $rows;
    }

    /** يتعرّف على سطر العناوين ويعيد موضع كل حقل فيه، أو null إن لم يكن سطر عناوين. */
    private function headerMap(array $cells): ?array
    {
        $map = [];
        $statusLabels = array_flip(MaintenanceWorkflow::LABELS);

        foreach ($cells as $position => $cell) {
            $header = $this->normalizeHeader($cell);
            if ($header === '') {
                continue;
            }
            if (isset($statusLabels[$header])) {
                $map['status:'.$statusLabels[$header]] = $position;
                continue;
            }
            foreach (self::HEADERS as $field => $names) {
                if (! isset($map[$field]) && in_array($header, $names, true)) {
                    $map[$field] = $position;
                    break;
                }
            }
        }

        return isset($map['name']) && count($map) >= 2 ? $map : null;
    }

    private function row(array $cells, array $map): ?array
    {
        $value = fn (string $field) => isset($map[$field]) ? $this->text($cells[$map[$field]] ?? null) : null;
        $name = $value('name');

        if (! $name || $this->isTotalRow($name)) {
            return null;
        }

        $unit = $value('unit');
        $type = null;
        $typeOrUnit = $value('type_or_unit');
        if ($typeOrUnit) {
            $isUnitWord = in_array(mb_strtolower($typeOrUnit), self::UNIT_WORDS, true);
            if (! isset($map['unit']) && $isUnitWord) {
                $unit = $typeOrUnit;
            } else {
                $type = $typeOrUnit;
            }
        }

        $notes = $value('notes');
        $partNumber = $value('part_number') ?: $this->extract('/رقم\s*الموديل\s+([A-Za-z0-9][A-Za-z0-9\-\.\/]*)/u', $notes);
        $brand = $value('brand') ?: $this->brandFrom($notes) ?: $this->brandFrom($name);

        $quantities = array_fill_keys(MaintenanceWorkflow::STATUSES, 0);
        $hasStatusColumns = false;
        foreach (MaintenanceWorkflow::STATUSES as $status) {
            if (isset($map["status:{$status}"])) {
                $hasStatusColumns = true;
                $quantities[$status] = $this->integer($cells[$map["status:{$status}"]] ?? null);
            }
        }
        if (! $hasStatusColumns) {
            $quantities['in_stock'] = $this->integer($cells[$map['quantity'] ?? -1] ?? null);
        }

        $price = $this->number($cells[$map['unit_price'] ?? -1] ?? null);
        $device = $value('device');

        // سطر لا يحمل إلا اسماً هو ترويسة أو توقيع، لا قطعة.
        if (! array_sum($quantities) && $price === null && ! $partNumber && ! $device && ! $unit && ! $type) {
            return null;
        }

        return [
            'name' => $name,
            'part_number' => $partNumber,
            'device' => $device,
            'unit' => $unit,
            'type' => $type,
            'brand' => $brand,
            'category' => $value('category'),
            'unit_price' => $price,
            'quantities' => $quantities,
            'location' => $value('location'),
            'min_quantity' => isset($map['min_quantity']) ? $this->integer($cells[$map['min_quantity']] ?? null) : null,
            'notes' => $notes,
        ];
    }

    /** أول نوع من أنواع الفئة يرد اسمه كلمةً كاملة في اسم القطعة: «ic power» ← IC. */
    private function guessType(string $name, array $types): ?string
    {
        foreach ($types as $type) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($type, '/').'(?![\p{L}\p{N}])/iu', $name)) {
                return $type;
            }
        }

        return null;
    }

    /** صورة القطعة: داخل خلية عمود الصورة، أو طافية مرساتها تلك الخلية. */
    private function imageAt(array $cells, array $map, $anchored, int $rowNumber): ?Drawing
    {
        if (! isset($map['image'])) {
            return null;
        }
        $cell = $cells[$map['image']] ?? null;

        return $cell instanceof Drawing
            ? $cell
            : $anchored->get(Coordinate::stringFromColumnIndex($map['image'] + 1).$rowNumber);
    }

    /** يحفظ صورة القطعة من داخل الملف إلى مجلد صور القطع، إن أمكنت قراءتها. */
    private function storeImage(?string $key): ?string
    {
        $drawing = $key ? ($this->images[$key] ?? null) : null;
        if (! $drawing) {
            return null;
        }

        try {
            $contents = file_get_contents($drawing->getPath());
        } catch (Throwable) {
            return null;
        }
        if (! $contents) {
            return null;
        }

        $path = 'maintenance-items/'.Str::uuid().'.'.(strtolower($drawing->getExtension()) ?: 'png');
        Storage::disk('public')->put($path, $contents);

        return $path;
    }

    private function brandFrom(?string $text): ?string
    {
        $brand = $this->extract('/(?<!\p{L})ماركة\s+([^\s()،,]+)/u', $text);

        return $brand && ! in_array($brand, self::NOT_BRANDS, true) ? $brand : null;
    }

    private function extract(string $pattern, ?string $text): ?string
    {
        return $text && preg_match($pattern, $text, $match) ? $match[1] : null;
    }

    private function isTotalRow(string $name): bool
    {
        $lower = mb_strtolower($name);
        foreach (self::TOTAL_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeader(mixed $cell): string
    {
        $text = $this->text($cell) ?? '';
        $text = preg_replace('/\(.*?\)/u', '', $text);

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
    }

    private function text(mixed $cell): ?string
    {
        // الصور المضمَّنة في الخلية تصل كائنات رسم، لا نصاً.
        if ($cell === null || is_bool($cell) || (is_object($cell) && ! $cell instanceof Stringable)) {
            return null;
        }
        $text = trim(preg_replace('/\s+/u', ' ', (string) $cell));

        // خلايا الصور المكسورة في ملفات الفرع تحمل #VALUE! ونحوها.
        return $text === '' || str_starts_with($text, '#') ? null : $text;
    }

    private function number(mixed $cell): ?float
    {
        if (is_numeric($cell)) {
            return round((float) $cell, 2);
        }
        $text = $this->text($cell);
        $clean = $text ? str_replace([',', '$', ' '], '', strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.'])) : null;

        return is_numeric($clean) ? round((float) $clean, 2) : null;
    }

    private function integer(mixed $cell): int
    {
        return max(0, (int) round($this->number($cell) ?? 0));
    }
}
