<?php

namespace App\Modules\Database\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Database\Requests\CreateBackupRequest;
use App\Modules\Database\Services\DatabaseBackupService;
use App\Modules\Database\Services\DatabaseTableRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    public function __construct(
        private DatabaseBackupService $backups,
        private DatabaseTableRegistry $tables,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->backups->listBackups());
    }

    public function tables(): JsonResponse
    {
        return response()->json($this->tables->backupTables());
    }

    public function backupTables(string $fileName): JsonResponse
    {
        return response()->json($this->backups->tablesInBackup($fileName));
    }

    public function internal(CreateBackupRequest $request): JsonResponse
    {
        $backup = $this->backups->createBackup(
            'internal', $request->validated('format') ?? 'json', $request->validated('tables'),
            (bool) $request->validated('bundle_files'),
        );

        return response()->json($backup, 201);
    }

    public function external(CreateBackupRequest $request): BinaryFileResponse
    {
        $backup = $this->backups->createBackup(
            'external', $request->validated('format') ?? 'json', $request->validated('tables'),
            (bool) $request->validated('bundle_files'),
        );

        return response()->download($this->backups->absolutePath($backup['file_name']), $this->backups->downloadName($backup['file_name']));
    }

    public function download(Request $request, string $fileName): BinaryFileResponse
    {
        abort_unless($this->backups->exists($fileName), 404);

        return response()->download($this->backups->absolutePath($fileName), $fileName);
    }

    public function destroy(string $fileName): JsonResponse
    {
        abort_unless($this->backups->exists($fileName), 404);
        $this->backups->delete($fileName);

        return response()->json(['deleted' => true, 'file_name' => $fileName]);
    }
}
