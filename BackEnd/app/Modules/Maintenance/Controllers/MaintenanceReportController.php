<?php

namespace App\Modules\Maintenance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceImport;
use App\Modules\Maintenance\Repositories\Interfaces\MaintenanceRepositoryInterface;
use App\Modules\Maintenance\Services\MaintenanceExportService;
use App\Modules\Maintenance\Services\MaintenanceImportService;
use App\Modules\Maintenance\Services\MaintenanceStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** الإحصاءات، والتصدير إلى Excel، ورفع ملفات الفرع إلى المستودع. */
class MaintenanceReportController extends Controller
{
    private const FILE_RULES = ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'];

    public function __construct(
        private MaintenanceRepositoryInterface $maintenance,
        private MaintenanceStatisticsService $statistics,
        private MaintenanceExportService $exporter,
        private MaintenanceImportService $importer,
    ) {}

    public function statistics(): JsonResponse
    {
        return response()->json($this->statistics->build());
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->exporter->export([
            'search' => $request->string('search')->toString(),
            'category_id' => $request->integer('category_id') ?: null,
            'type_id' => $request->integer('type_id') ?: null,
            'brand_id' => $request->integer('brand_id') ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'low_stock' => $request->boolean('low_stock'),
        ]);
    }

    public function imports(): JsonResponse
    {
        return response()->json($this->maintenance->imports());
    }

    public function previewImport(Request $request): JsonResponse
    {
        $data = $this->validateImport($request);

        return response()->json($this->importer->preview($request->file('file'), $data['category_id'] ?? null, $data['existing'] ?? 'skip'));
    }

    public function commitImport(Request $request): JsonResponse
    {
        $data = $this->validateImport($request);
        $import = $this->importer->commit($request->file('file'), $data['category_id'] ?? null, $data['existing'] ?? 'skip', $request->user());

        return response()->json($import, 201);
    }

    public function downloadImport(MaintenanceImport $import)
    {
        abort_unless(Storage::disk('public')->exists($import->file_path), 404, 'الملف الأصلي غير موجود على الخادم.');

        return Storage::disk('public')->download($import->file_path, $import->file_name);
    }

    /** حذف ملف مرفوع دون المساس بما أُخذ منه إلى المستودع. */
    public function destroyImport(MaintenanceImport $import): JsonResponse
    {
        $this->maintenance->deleteImport($import);

        return response()->json(['deleted' => true]);
    }

    private function validateImport(Request $request): array
    {
        return $request->validate([
            'file' => self::FILE_RULES,
            'category_id' => ['nullable', 'integer', 'exists:maintenance_categories,id'],
            'existing' => ['nullable', 'in:skip,add'],
        ], [
            'file.mimes' => 'الملف يجب أن يكون Excel (xlsx أو xls) أو csv.',
            'file.max' => 'حجم الملف أكبر من 10 ميغابايت.',
        ]);
    }
}
