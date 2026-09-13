<?php

namespace App\Modules\Database\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Database\Repositories\Interfaces\DatabaseRepositoryInterface;
use App\Modules\Database\Requests\RestoreDatabaseRequest;
use App\Modules\Database\Requests\TruncateDatabaseRequest;
use App\Modules\Database\Services\DatabaseRestoreService;
use App\Modules\Database\Services\DatabaseTruncateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class DatabaseMaintenanceController extends Controller
{
    public function __construct(
        private DatabaseTruncateService $truncate,
        private DatabaseRestoreService $restore,
        private DatabaseRepositoryInterface $database,
    ) {}

    public function truncate(TruncateDatabaseRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => __('messages.invalid_credentials')], 422);
        }

        $requested = $request->validated('tables');
        $tables = $requested === ['all'] ? $this->truncate->truncateAll() : $this->truncate->truncate($requested);

        $this->database->logMaintenance([
            'user_id' => $user->id,
            'action' => 'truncate',
            'tables' => $tables,
            'created_at' => now(),
        ]);

        return response()->json(['truncated' => $tables]);
    }

    public function restore(RestoreDatabaseRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => __('messages.invalid_credentials')], 422);
        }

        $fileName = $request->validated('file_name');
        $restoreFiles = $request->validated('restore_files');
        $summary = $this->restore->restore($fileName, $request->validated('tables'), $restoreFiles === null ? true : (bool) $restoreFiles);

        $this->database->logMaintenance([
            'user_id' => $user->id,
            'action' => 'restore',
            'tables' => $summary['tables'],
            'source_file' => $fileName,
            'created_at' => now(),
        ]);

        return response()->json(['restored' => $summary['tables'], 'files' => $summary['files']]);
    }

    public function logs(): JsonResponse
    {
        return response()->json($this->database->maintenanceLogs(100));
    }
}
