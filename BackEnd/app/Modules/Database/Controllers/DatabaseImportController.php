<?php

namespace App\Modules\Database\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Database\Requests\ImportExcelRequest;
use App\Modules\Database\Services\DatabaseTableRegistry;
use App\Modules\Database\Services\ExcelImportService;
use Illuminate\Http\JsonResponse;

class DatabaseImportController extends Controller
{
    public function __construct(
        private ExcelImportService $importer,
        private DatabaseTableRegistry $tables,
    ) {}

    /** الجداول المسموح استيرادها فقط — تستبعد tasks تلقائيًا (DatabaseTableRegistry::IMPORT_BLOCKED_TABLES). */
    public function importableTables(): JsonResponse
    {
        return response()->json($this->tables->importableTables());
    }

    public function preview(ImportExcelRequest $request, string $table): JsonResponse
    {
        return response()->json($this->importer->preview($table, $request->file('file')));
    }

    public function commit(ImportExcelRequest $request, string $table): JsonResponse
    {
        return response()->json($this->importer->commit($table, $request->file('file')));
    }
}
