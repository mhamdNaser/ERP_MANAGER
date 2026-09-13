<?php

namespace App\Modules\Drive\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DriveFile;
use App\Models\DriveFolder;
use App\Models\User;
use App\Modules\Drive\Repositories\Interfaces\DriveRepositoryInterface;
use App\Modules\Drive\Resources\DriveFileResource;
use App\Modules\Drive\Resources\DriveFolderResource;
use App\Modules\Tasks\Services\TaskActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DriveController extends Controller
{
    private const MAX_UPLOAD_FILES = 100;
    private const MAX_UPLOAD_FILE_KILOBYTES = 102400;
    private const MAX_UPLOAD_TOTAL_BYTES = 1073741824;

    private const QUOTA_ROLES = ['employee', 'technician', 'department_head', 'branch_manager', 'general_manager', 'database_manager'];

    public function __construct(
        private DriveRepositoryInterface $drive,
        private TaskActivityService $activities,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'files' => DriveFileResource::collection($this->drive->visibleFiles($user)),
            'folders' => DriveFolderResource::collection($this->drive->visibleFolders($user)),
            'departments' => $this->drive->departmentsFor($user),
            'recipients' => $this->drive->recipientsFor($user),
            'storage_quota' => $this->storageQuotaFor($user),
            'role_quotas' => $user->primaryRole() === 'database_manager' ? $this->roleQuotas() : [],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:'.self::MAX_UPLOAD_FILES],
            'files.*' => ['required', 'file', 'max:'.self::MAX_UPLOAD_FILE_KILOBYTES],
            'scope' => ['required', Rule::in(['personal', 'department', 'task', 'organization'])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'folder_id' => ['nullable', 'integer', 'exists:drive_folders,id'],
        ]);

        $user = $request->user();
        $incomingSize = collect($request->file('files'))->sum(fn ($file) => $file->getSize());

        abort_if($incomingSize > self::MAX_UPLOAD_TOTAL_BYTES, 422, 'الحجم الإجمالي للملفات في عملية الرفع الواحدة يجب ألا يتجاوز 1GB.');

        if ($data['scope'] === 'organization') {
            abort_unless($user->primaryRole() === 'database_manager', 403);
        }

        $folder = isset($data['folder_id']) ? $this->drive->findFolder($data['folder_id']) : null;

        if ($folder) {
            $this->authorizeControlFolder($user, $folder);
        }

        $scope = $folder?->scope ?? $data['scope'];

        $departmentId = $folder?->department_id ?? ($data['department_id'] ?? $user->department_id);

        $task = isset($data['task_id']) ? $this->drive->findTask($data['task_id']) : null;

        if (! in_array($scope, ['personal', 'organization'], true)) {
            abort_unless($departmentId && $this->drive->canAccessDepartment($user, $departmentId), 403);
        }

        if ($task) {
            abort_unless($this->drive->canAccessDepartment($user, $task->department_id), 403);
            $departmentId = $task->department_id;
        }

        $this->ensureQuotaAllowsUpload($user, $incomingSize);

        $created = collect($request->file('files'))->map(function ($uploaded) use ($user, $data, $departmentId, $task, $folder, $scope) {
            $directory = $folder
                ? $this->ensureFolderPath($folder)
                : "drive/users/{$user->id}";

            $this->makeDirectory('local', $directory);

            $storedName = $this->uniqueName(
                'local',
                $directory,
                $uploaded->getClientOriginalName()
            );

            $storedPath = $uploaded->storeAs($directory, $storedName, 'local');

            if ($storedPath === false) {
                throw new \RuntimeException("Unable to store uploaded file in [{$directory}].");
            }

            $file = $this->drive->createFile([
                'uploader_id' => $user->id,
                'department_id' => in_array($scope, ['personal', 'organization'], true) ? null : $departmentId,
                'task_id' => $task?->id,
                'folder_id' => $folder?->id,
                'scope' => $scope,
                'name' => $uploaded->getClientOriginalName(),
                'path' => $storedPath,
                'mime_type' => $uploaded->getClientMimeType(),
                'size' => $uploaded->getSize(),
                'description' => $data['description'] ?? null,
            ]);
            if ($task) {
                $this->activities->record($task, $user, 'file_attached', 'أرفق ملفاً بالمهمة.', [
                    'file_id' => $file->id,
                    'file_name' => $file->name,
                    'file_size' => $file->size,
                ]);
            }

            if ($folder?->public_token) {
                $this->publishFile($file);
            }

            return $this->drive->loadFile($file);
        });

        return response()->json(DriveFileResource::collection($created), 201);
    }

    public function storeFolder(Request $request): DriveFolderResource|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scope' => ['required', Rule::in(['personal', 'department', 'organization'])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'parent_id' => ['nullable', 'integer', 'exists:drive_folders,id'],
        ]);

        $user = $request->user();

        if ($data['scope'] === 'organization') {
            abort_unless($user->primaryRole() === 'database_manager', 403);
        }

        $parent = isset($data['parent_id']) ? $this->drive->findFolder($data['parent_id']) : null;

        if ($parent) {
            $this->authorizeControlFolder($user, $parent);
        }

        $scope = $parent?->scope ?? $data['scope'];

        $departmentId = $parent?->department_id ?? ($data['department_id'] ?? $user->department_id);

        if (! in_array($scope, ['personal', 'organization'], true)) {
            abort_unless($departmentId && $this->drive->canAccessDepartment($user, $departmentId), 403);
        }

        $basePath = $parent
            ? $this->ensureFolderPath($parent)
            : "drive/users/{$user->id}";

        $this->makeDirectory('local', $basePath);

        $path = $basePath . '/' . $this->uniqueDirectoryName(
            'local',
            $basePath,
            $data['name']
        );

        $this->makeDirectory('local', $path);

        $folder = $this->drive->createFolder([
            'owner_id' => $user->id,
            'parent_id' => $parent?->id,
            'department_id' => $scope === 'department' ? $departmentId : null,
            'scope' => $scope,
            'name' => $data['name'],
            'path' => $path,
            'description' => $data['description'] ?? null,
        ]);

        if ($parent?->public_token) {
            $this->publishFolder($folder);
        }

        return new DriveFolderResource($this->drive->loadFolder($folder));
    }

    public function share(Request $request, DriveFile $file): DriveFileResource
    {
        $this->authorizeControl($request->user(), $file);
        $this->drive->shareFile($file, $this->drive->shareRecipientIds($request->user(), $this->shareCriteria($request)), $request->user()->id);

        return new DriveFileResource($this->drive->loadFileSharing($file));
    }

    public function shareFolder(Request $request, DriveFolder $folder): DriveFolderResource
    {
        $this->authorizeControlFolder($request->user(), $folder);
        $this->drive->shareFolder($folder, $this->drive->shareRecipientIds($request->user(), $this->shareCriteria($request)), $request->user()->id);

        return new DriveFolderResource($this->drive->loadFolderSharing($folder));
    }

    public function revokeShare(Request $request, DriveFile $file): DriveFileResource
    {
        $this->authorizeControl($request->user(), $file);
        $this->drive->revokeFileShare($file, $this->shareCriteria($request));

        return new DriveFileResource($this->drive->loadFileSharing($file));
    }

    public function revokeFolderShare(Request $request, DriveFolder $folder): DriveFolderResource
    {
        $this->authorizeControlFolder($request->user(), $folder);
        $this->drive->revokeFolderShare($folder, $this->shareCriteria($request));

        return new DriveFolderResource($this->drive->loadFolderSharing($folder));
    }

    public function roleQuotasIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->primaryRole() === 'database_manager', 403);

        return response()->json($this->roleQuotas());
    }

    public function updateRoleQuotas(Request $request): JsonResponse
    {
        abort_unless($request->user()->primaryRole() === 'database_manager', 403);
        abort_unless($this->drive->hasRoleQuotaTable(), 500, 'يجب تنفيذ ترحيل قاعدة البيانات الخاص بسعات الدرايف أولاً.');
        $data = $request->validate([
            'quotas' => ['required', 'array'],
            'quotas.*.role' => ['required', 'string', 'max:80'],
            'quotas.*.quota_bytes' => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ($data['quotas'] as $quota) {
            if ($quota['role'] === 'database_manager') continue;
            $this->drive->saveRoleQuota($quota['role'], $quota['quota_bytes']);
        }

        return response()->json($this->roleQuotas());
    }

    public function archive(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $fileIds = collect($request->input('file_ids', []))->map(fn ($id) => (int) $id)->filter()->values()->all();
        $folderIds = collect($request->input('folder_ids', []))->map(fn ($id) => (int) $id)->filter()->values()->all();

        $files = $this->drive->visibleFilesFor($user, $fileIds, $folderIds);

        abort_if($files->isEmpty(), 404, 'لا توجد ملفات متاحة للتنزيل.');

        $zipName = 'drive-archive-'.now()->format('Y-m-d-His').'.zip';
        $zipPath = storage_path("app/private/tmp/{$zipName}");
        $this->makeDirectory('local', 'tmp');

        $zip = new ZipArchive();
        abort_unless($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500);
        foreach ($files as $file) {
            if (! Storage::disk('local')->exists($file->path)) continue;
            $zip->addFile(Storage::disk('local')->path($file->path), $this->archiveEntryName($file));
        }
        $zip->close();

        return response()->download($zipPath, $zipName, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    public function publicLink(Request $request, DriveFile $file): DriveFileResource
    {
        $this->authorizeControl($request->user(), $file);
        $this->publishFile($file);

        return new DriveFileResource($this->drive->loadFile($file, true));
    }

    public function revokePublicLink(Request $request, DriveFile $file): DriveFileResource
    {
        $this->authorizeControl($request->user(), $file);
        $this->unpublishFile($file);

        return new DriveFileResource($this->drive->loadFile($file, true));
    }

    public function publicFolderLink(Request $request, DriveFolder $folder): DriveFolderResource
    {
        $this->authorizeControlFolder($request->user(), $folder);
        $this->setFolderPublicTokens($folder, true);

        return new DriveFolderResource($this->drive->loadFolder($folder->fresh()));
    }

    public function revokePublicFolderLink(Request $request, DriveFolder $folder): DriveFolderResource
    {
        $this->authorizeControlFolder($request->user(), $folder);
        $this->setFolderPublicTokens($folder, false);

        return new DriveFolderResource($this->drive->loadFolder($folder->fresh()));
    }

    public function download(Request $request, DriveFile $file): BinaryFileResponse
    {
        abort_unless($this->drive->userSeesFile($request->user(), $file), 403);

        return response()->download(Storage::disk('local')->path($file->path), $file->name);
    }

    public function preview(Request $request, DriveFile $file): StreamedResponse
    {
        abort_unless($this->drive->userSeesFile($request->user(), $file), 403);

        abort_unless(Storage::disk('local')->exists($file->path), 404, 'الملف غير موجود على التخزين.');

        $stream = Storage::disk('local')->readStream($file->path);
        abort_unless(is_resource($stream), 500, 'تعذر قراءة الملف للمعاينة.');

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => $this->inlineDisposition($file->name),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function publicDownload(Request $request, string $token): BinaryFileResponse
    {
        $file = $this->drive->fileByToken($token);
        if ($request->boolean('inline')) {
            return response()->file(Storage::disk('local')->path($file->path), [
                'Content-Type' => $file->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes($file->name).'"',
            ]);
        }
        return response()->download(Storage::disk('local')->path($file->path), $file->name);
    }

    private function inlineDisposition(string $filename): string
    {
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'preview';

        return 'inline; filename="'.$fallback.'"; filename*=UTF-8\'\''.rawurlencode($filename);
    }

    public function publicFolder(string $token): JsonResponse
    {
        $folder = $this->drive->folderByToken($token);
        $listing = $this->drive->publicFolderListing($folder, $this->drive->descendantFolderIds($folder, true));

        return response()->json([
            'folder' => $folder->only(['name', 'description']),
            'folders' => DriveFolderResource::collection($listing['folders']),
            'files' => DriveFileResource::collection($listing['files']),
        ]);
    }

    public function destroy(Request $request, DriveFile $file): JsonResponse
    {
        $this->authorizeControl($request->user(), $file);
        $task = $file->task;
        $fileDetails = ['file_id' => $file->id, 'file_name' => $file->name, 'file_size' => $file->size];
        Storage::disk('local')->delete($file->path);
        if ($file->public_path) Storage::disk('public')->delete($file->public_path);
        if ($task) $this->activities->record($task, $request->user(), 'file_deleted', 'حذف ملفاً مرفقاً بالمهمة.', $fileDetails);
        $this->drive->deleteFile($file);

        return response()->json(['message' => 'تم حذف الملف.']);
    }

    public function destroyFolder(Request $request, DriveFolder $folder): JsonResponse
    {
        $this->authorizeControlFolder($request->user(), $folder);
        $ids = $this->drive->descendantFolderIds($folder, true);
        $this->drive->filesInFolders($ids)->each(function (DriveFile $file) {
            Storage::disk('local')->delete($file->path);
            if ($file->public_path) Storage::disk('public')->delete($file->public_path);
        });
        if ($folder->path) Storage::disk('local')->deleteDirectory($folder->path);
        if ($folder->public_path) Storage::disk('public')->deleteDirectory($folder->public_path);
        $this->drive->deleteFolder($folder);

        return response()->json(['message' => 'تم حذف المجلد ومحتوياته.']);
    }

    /** مدخلات المشاركة والسحب متطابقة، فتُتحقَّق في موضع واحد. */
    private function shareCriteria(Request $request): array
    {
        return $request->validate([
            'target' => ['required', Rule::in(['users', 'department', 'branch'])],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
    }

    private function authorizeControl(User $user, DriveFile $file): void
    {
        $role = $user->primaryRole();
        $allowed = $file->uploader_id === $user->id
            || $role === 'general_manager'
            || ($role === 'department_head' && $file->uploader?->department_id === $user->department_id)
            || ($role === 'branch_manager' && $file->uploader?->branch_id === $user->branch_id);
        abort_unless($allowed, 403);
    }

    private function authorizeControlFolder(User $user, DriveFolder $folder): void
    {
        $role = $user->primaryRole();
        $allowed = $folder->owner_id === $user->id || $role === 'general_manager'
            || ($role === 'department_head' && $folder->owner?->department_id === $user->department_id)
            || ($role === 'branch_manager' && $folder->owner?->branch_id === $user->branch_id);
        abort_unless($allowed, 403);
    }

    private function roleQuotas(): array
    {
        $rows = $this->drive->roleQuotaMap();

        return collect(self::QUOTA_ROLES)->map(fn (string $role) => [
            'role' => $role,
            'quota_bytes' => $role === 'database_manager'
                ? null
                : ($rows[$role] ?? $this->defaultQuotaBytes()),
        ])->values()->all();
    }

    private function storageQuotaFor(User $user): array
    {
        $used = $this->drive->usedBytes($user);
        $quota = $user->primaryRole() === 'database_manager'
            ? null
            : $this->quotaBytesForRole($user->primaryRole());

        return [
            'used_bytes' => $used,
            'quota_bytes' => $quota,
            'remaining_bytes' => $quota === null ? null : max(0, $quota - $used),
            'percent' => $quota ? min(100, round(($used / $quota) * 100, 1)) : 0,
            'unlimited' => $quota === null,
        ];
    }

    private function ensureQuotaAllowsUpload(User $user, int $incomingBytes): void
    {
        if ($user->primaryRole() === 'database_manager') return;
        $quota = $this->quotaBytesForRole($user->primaryRole());
        $used = $this->drive->usedBytes($user);

        abort_if($used + $incomingBytes > $quota, 422, 'المساحة التخزينية المتاحة لهذا الحساب لا تكفي لرفع الملفات المحددة.');
    }

    private function quotaBytesForRole(string $role): int
    {
        if (! $this->drive->hasRoleQuotaTable()) return $this->defaultQuotaBytes();

        return $this->drive->roleQuotaBytes($role) ?? $this->defaultQuotaBytes();
    }

    private function defaultQuotaBytes(): int
    {
        return 3 * 1024 * 1024 * 1024;
    }

    private function archiveEntryName(DriveFile $file): string
    {
        $segments = [];
        $folder = $file->folder;
        while ($folder) {
            array_unshift($segments, $this->safeSegment($folder->name));
            $folder = $folder->parent;
        }

        $name = $this->safeSegment($file->name);
        return collect([...$segments, "{$file->id}-{$name}"])->filter()->join('/');
    }

    private function setFolderPublicTokens(DriveFolder $folder, bool $enabled): void
    {
        $ids = $this->drive->descendantFolderIds($folder, true);
        $folders = $this->drive->foldersIn($ids);
        $files = $this->drive->filesInFolders($ids);
        if ($enabled) {
            $folders->each(fn (DriveFolder $item) => $this->publishFolder($item));
            $files->each(fn (DriveFile $file) => $this->publishFile($file));
            return;
        }
        if ($folder->public_path) Storage::disk('public')->deleteDirectory($folder->public_path);
        $folders->each(fn (DriveFolder $item) => $this->drive->updateFolder($item, ['public_token' => null, 'public_path' => null]));
        $files->each(fn (DriveFile $file) => $this->drive->updateFile($file, ['public_token' => null, 'public_path' => null]));
    }

    private function ensureFolderPath(DriveFolder $folder): string
    {
        if ($folder->path) {
            $this->makeDirectory('local', $folder->path);
            return $folder->path;
        }
        $basePath = $folder->parent ? $this->ensureFolderPath($folder->parent) : "drive/users/{$folder->owner_id}";
        $this->makeDirectory('local', $basePath);
        $path = $basePath.'/'.$this->uniqueDirectoryName('local', $basePath, $folder->name);
        $this->makeDirectory('local', $path);
        $this->drive->updateFolder($folder, ['path' => $path]);
        return $path;
    }

    private function publishFolder(DriveFolder $folder): void
    {
        $path = $this->ensureFolderPath($folder);
        $publicPath = 'drive-public/'.$path;
        $this->makeDirectory('public', $publicPath);
        $this->drive->updateFolder($folder, [
            'public_token' => $folder->public_token ?: Str::random(64),
            'public_path' => $publicPath,
        ]);
    }

    private function publishFile(DriveFile $file): void
    {
        $publicPath = 'drive-public/'.$file->path;
        $this->makeDirectory('public', dirname($publicPath));
        $stream = Storage::disk('local')->readStream($file->path);

        if ($stream === false) {
            throw new \RuntimeException("Unable to read drive file [{$file->path}].");
        }

        $stored = Storage::disk('public')->put($publicPath, $stream);
        if (is_resource($stream)) fclose($stream);

        if (! $stored) {
            throw new \RuntimeException("Unable to publish drive file [{$publicPath}].");
        }

        $this->drive->updateFile($file, [
            'public_token' => $file->public_token ?: Str::random(64),
            'public_path' => $publicPath,
        ]);
    }

    private function unpublishFile(DriveFile $file): void
    {
        if ($file->public_path) Storage::disk('public')->delete($file->public_path);
        $this->drive->updateFile($file, ['public_token' => null, 'public_path' => null]);
    }

    private function uniqueName(string $disk, string $directory, string $originalName): string
    {
        $safeName = $this->safeSegment(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = $this->safeSegment(pathinfo($originalName, PATHINFO_EXTENSION));
        $candidate = $safeName.($extension ? ".{$extension}" : '');
        $counter = 2;
        while (Storage::disk($disk)->exists("{$directory}/{$candidate}")) {
            $candidate = "{$safeName} ({$counter})".($extension ? ".{$extension}" : '');
            $counter++;
        }
        return $candidate;
    }

    private function uniqueDirectoryName(string $disk, string $directory, string $name): string
    {
        $safeName = $this->safeSegment($name);
        $candidate = $safeName;
        $counter = 2;
        while (Storage::disk($disk)->exists("{$directory}/{$candidate}")) {
            $candidate = "{$safeName} ({$counter})";
            $counter++;
        }
        return $candidate;
    }

    private function safeSegment(string $value): string
    {
        $value = trim(preg_replace('/[\\\\\/:*?"<>|]+/u', '-', $value) ?? '', " .\t\n\r\0\x0B");
        return $value !== '' ? $value : 'بدون اسم';
    }

    private function makeDirectory(string $disk, string $path): void
    {
        if (! Storage::disk($disk)->makeDirectory($path)) {
            throw new \RuntimeException("Unable to create directory [{$disk}:{$path}].");
        }
    }
}
