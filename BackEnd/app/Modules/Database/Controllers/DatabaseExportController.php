<?php

namespace App\Modules\Database\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Database\Services\ExcelExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseExportController extends Controller
{
    public function __construct(private ExcelExportService $excel) {}

    public function export(string $table): StreamedResponse
    {
        return $this->excel->export($table);
    }
}
