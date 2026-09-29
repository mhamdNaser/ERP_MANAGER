<?php

namespace App\Modules\Database\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Database\Requests\CreateBackupRequest;
use App\Modules\Database\Services\BackupPresetRegistry;
use App\Modules\Database\Services\DatabaseBackupService;
use App\Modules\Database\Services\DatabaseTableRegistry;
use App\Modules\Database\Services\MigrationPackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    public function __construct(
        private DatabaseBackupService $backups,
        private DatabaseTableRegistry $tables,
        private BackupPresetRegistry $presets,
        private MigrationPackageService $packages,
    ) {}

    public function presets(): JsonResponse
    {
        return response()->json($this->presets->all());
    }

    /**
     * حزمة ترحيل كاملة — تُبنى وتُحفظ بين النسخ فتُنزَّل من القائمة.
     * بناؤها قد يطول على مؤسسة كبيرة الملفات، ولذلك يوجد أيضاً أمر
     * cnd:migration-package لتشغيلها عبر SSH بلا مهلة طلب.
     */
    public function migrationPackage(Request $request): JsonResponse
    {
        $label = $request->string('label')->toString();
        $package = $this->packages->build($label !== '' ? preg_replace('/[^a-z0-9-]/i', '', $label) : null);

        return response()->json($package, 201);
    }

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
        return response()->json($this->create('internal', $request), 201);
    }

    /** الحزمة الجاهزة تفرض جداولها ومرشِّحاتها وتضمّ الملفات دائماً. */
    private function create(string $kind, CreateBackupRequest $request): array
    {
        $preset = $request->validated('preset');

        if ($preset === null) {
            return $this->backups->createBackup(
                $kind, $request->validated('format') ?? 'json', $request->validated('tables'),
                (bool) $request->validated('bundle_files'),
            );
        }

        abort_unless($this->presets->has($preset), 422, "حزمة غير معروفة: {$preset}");

        return $this->backups->createBackup(
            $kind, 'json', $this->presets->tables($preset), true, $this->presets->rowFilters($preset),
        ) + ['preset' => $preset];
    }

    public function external(CreateBackupRequest $request): BinaryFileResponse
    {
        $backup = $this->create('external', $request);

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
