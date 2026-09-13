<?php
namespace App\Modules\Drive\Repositories\Eloquent;

use App\Models\Department;
use App\Models\DriveFile;
use App\Models\DriveFolder;
use App\Models\Task;
use App\Models\User;
use App\Modules\Drive\Repositories\Interfaces\DriveRepositoryInterface;
use App\Modules\Organization\Services\OrganizationScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DriveRepository implements DriveRepositoryInterface
{
    private const SHARED_USER_RELATIONS = [
        'sharedUsers:id,name,job_title,branch_id,department_id',
        'sharedUsers.branch:id,name',
        'sharedUsers.department:id,name',
    ];

    private const FILE_RELATIONS = ['uploader:id,name,role', 'department:id,name', 'task:id,title'];

    private const FOLDER_RELATIONS = ['owner:id,name,role', 'department:id,name'];

    private const FOLDER_COUNTS = ['files', 'children', 'sharedUsers'];

    public function __construct(private OrganizationScopeService $scope) {}

    public function visibleFiles(User $user): Collection
    {
        return $this->visibleFileQuery($user)
            ->with([...self::FILE_RELATIONS, ...self::SHARED_USER_RELATIONS])
            ->withCount('sharedUsers')
            ->latest()
            ->get();
    }

    public function visibleFolders(User $user): Collection
    {
        return DriveFolder::whereIn('id', $this->visibleFolderIds($user))
            ->with([...self::FOLDER_RELATIONS, ...self::SHARED_USER_RELATIONS])
            ->withCount(self::FOLDER_COUNTS)
            ->latest()
            ->get();
    }

    public function userSeesFile(User $user, DriveFile $file): bool
    {
        return $this->visibleFileQuery($user)->whereKey($file->id)->exists();
    }

    public function visibleFilesFor(User $user, array $fileIds, array $folderIds): Collection
    {
        $files = $this->visibleFileQuery($user)
            ->when($fileIds !== [], fn (Builder $query) => $query->whereIn('id', $fileIds))
            ->get();

        if ($folderIds === []) {
            return $files;
        }

        // مجلدات مختارة: تُضاف ملفات المجلد وكل ما تحته، بعد حصرها بالمسموح رؤيته.
        $allowed = $this->visibleFolderIds($user);
        $nested = DriveFolder::whereIn('id', $folderIds)->whereIn('id', $allowed)->get()
            ->flatMap(fn (DriveFolder $folder) => $this->descendantFolderIds($folder, true))
            ->unique()
            ->values();

        return $files->merge($this->visibleFileQuery($user)->whereIn('folder_id', $nested)->get())
            ->unique('id')
            ->values();
    }

    public function visibleFolderIds(User $user): array
    {
        $role = $user->primaryRole();
        $folders = DriveFolder::with(['owner:id,branch_id,department_id,role', 'sharedUsers:id'])->get();

        $visible = $folders->filter(function (DriveFolder $folder) use ($user, $role) {
            return $folder->owner_id === $user->id
                || ($folder->scope === 'organization' && $folder->owner?->role !== 'database_manager')
                || ($folder->scope === 'department' && $folder->department_id === $user->department_id)
                || $folder->sharedUsers->contains('id', $user->id)
                || $role === 'general_manager'
                || ($role === 'department_head' && $folder->owner?->department_id === $user->department_id)
                || ($role === 'branch_manager' && $folder->owner?->branch_id === $user->branch_id);
        })->pluck('id')->all();

        // أي مجلد داخل مجلد مرئي مرئيٌّ بدوره — يتكرر الضمّ حتى يثبت العدد.
        do {
            $before = count($visible);
            $visible = array_values(array_unique([...$visible, ...$folders->whereIn('parent_id', $visible)->pluck('id')->all()]));
        } while (count($visible) > $before);

        return $visible;
    }

    public function departmentsFor(User $user): Collection
    {
        $departments = $this->scope->departments($user)->with('branch:id,name')->get(['id', 'name', 'branch_id']);

        if (! $departments->count() && $user->department_id) {
            return Department::with('branch:id,name')->whereKey($user->department_id)->get(['id', 'name', 'branch_id']);
        }

        return $departments;
    }

    public function canAccessDepartment(User $user, int $departmentId): bool
    {
        return $user->department_id === $departmentId || $this->scope->departments($user)->whereKey($departmentId)->exists();
    }

    public function recipientsFor(User $user): Collection
    {
        return $this->recipientQuery($user)
            ->where('is_active', true)
            ->with(['branch:id,name', 'department:id,name'])
            ->get(['id', 'name', 'job_title', 'branch_id', 'department_id']);
    }

    public function shareRecipientIds(User $actor, array $criteria): BaseCollection
    {
        $query = $this->recipientQuery($actor)->where('is_active', true);

        if ($criteria['target'] === 'users') $query->whereIn('id', $criteria['user_ids'] ?? []);
        if ($criteria['target'] === 'department') $query->where('department_id', $criteria['department_id'] ?? 0);
        if ($criteria['target'] === 'branch') $query->where('branch_id', $criteria['branch_id'] ?? 0);

        return $query->whereKeyNot($actor->id)->pluck('id');
    }

    public function findFolder(int $folderId): DriveFolder
    {
        return DriveFolder::findOrFail($folderId);
    }

    public function findTask(int $taskId): Task
    {
        return Task::findOrFail($taskId);
    }

    public function fileByToken(string $token): DriveFile
    {
        return DriveFile::where('public_token', $token)->firstOrFail();
    }

    public function folderByToken(string $token): DriveFolder
    {
        return DriveFolder::where('public_token', $token)->firstOrFail();
    }

    public function descendantFolderIds(DriveFolder $folder, bool $includeSelf = false): array
    {
        $all = DriveFolder::get(['id', 'parent_id']);
        $ids = $includeSelf ? [$folder->id] : [];
        $parents = [$folder->id];

        while ($parents) {
            $children = $all->whereIn('parent_id', $parents)->pluck('id')->all();
            $ids = [...$ids, ...$children];
            $parents = $children;
        }

        return array_values(array_unique($ids));
    }

    public function foldersIn(array $folderIds): Collection
    {
        return DriveFolder::whereIn('id', $folderIds)->get();
    }

    public function filesInFolders(array $folderIds): Collection
    {
        return DriveFile::whereIn('folder_id', $folderIds)->get();
    }

    public function publicFolderListing(DriveFolder $folder, array $folderIds): array
    {
        return [
            'folders' => DriveFolder::whereIn('id', $folderIds)->whereKeyNot($folder->id)
                ->with('owner:id,name,role')->withCount(self::FOLDER_COUNTS)->get(),
            'files' => DriveFile::whereIn('folder_id', $folderIds)
                ->with('uploader:id,name,role')->withCount('sharedUsers')->get(),
        ];
    }

    public function createFile(array $attributes): DriveFile
    {
        return DriveFile::create($attributes);
    }

    public function createFolder(array $attributes): DriveFolder
    {
        return DriveFolder::create($attributes);
    }

    public function updateFile(DriveFile $file, array $attributes): DriveFile
    {
        $file->update($attributes);

        return $file;
    }

    public function updateFolder(DriveFolder $folder, array $attributes): DriveFolder
    {
        $folder->update($attributes);

        return $folder;
    }

    public function deleteFile(DriveFile $file): void
    {
        $file->delete();
    }

    public function deleteFolder(DriveFolder $folder): void
    {
        $folder->delete();
    }

    public function loadFile(DriveFile $file, bool $withShareCount = false): DriveFile
    {
        $file->load(self::FILE_RELATIONS);

        return $withShareCount ? $file->loadCount('sharedUsers') : $file;
    }

    public function loadFileSharing(DriveFile $file): DriveFile
    {
        return $file->fresh()->load([...self::FILE_RELATIONS, ...self::SHARED_USER_RELATIONS])->loadCount('sharedUsers');
    }

    public function loadFolder(DriveFolder $folder): DriveFolder
    {
        return $folder->load(self::FOLDER_RELATIONS)->loadCount(self::FOLDER_COUNTS);
    }

    public function loadFolderSharing(DriveFolder $folder): DriveFolder
    {
        return $folder->fresh()->load([...self::FOLDER_RELATIONS, ...self::SHARED_USER_RELATIONS])->loadCount(self::FOLDER_COUNTS);
    }

    public function shareFile(DriveFile $file, BaseCollection $userIds, int $sharedBy): void
    {
        $file->sharedUsers()->syncWithoutDetaching($this->sharePivot($userIds, $sharedBy));
    }

    public function shareFolder(DriveFolder $folder, BaseCollection $userIds, int $sharedBy): void
    {
        $folder->sharedUsers()->syncWithoutDetaching($this->sharePivot($userIds, $sharedBy));
    }

    public function revokeFileShare(DriveFile $file, array $criteria): void
    {
        $file->sharedUsers()->detach($this->sharedUserIds($file->sharedUsers(), $criteria));
    }

    public function revokeFolderShare(DriveFolder $folder, array $criteria): void
    {
        $folder->sharedUsers()->detach($this->sharedUserIds($folder->sharedUsers(), $criteria));
    }

    public function usedBytes(User $user): int
    {
        return (int) DriveFile::where('uploader_id', $user->id)->sum('size');
    }

    public function hasRoleQuotaTable(): bool
    {
        return Schema::hasTable('role_drive_quotas');
    }

    public function roleQuotaMap(): BaseCollection
    {
        return $this->hasRoleQuotaTable() ? DB::table('role_drive_quotas')->pluck('quota_bytes', 'role') : collect();
    }

    public function roleQuotaBytes(string $role): ?int
    {
        $value = DB::table('role_drive_quotas')->where('role', $role)->value('quota_bytes');

        return $value === null ? null : (int) $value;
    }

    public function saveRoleQuota(string $role, ?int $quotaBytes): void
    {
        DB::table('role_drive_quotas')->updateOrInsert(
            ['role' => $role],
            ['quota_bytes' => $quotaBytes, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    private function sharePivot(BaseCollection $userIds, int $sharedBy): array
    {
        return $userIds->mapWithKeys(fn ($id) => [$id => ['shared_by' => $sharedBy]])->all();
    }

    private function sharedUserIds($relation, array $criteria): BaseCollection
    {
        $query = $relation->select('users.id');

        if ($criteria['target'] === 'users') $query->whereIn('users.id', $criteria['user_ids'] ?? []);
        if ($criteria['target'] === 'department') $query->where('users.department_id', $criteria['department_id'] ?? 0);
        if ($criteria['target'] === 'branch') $query->where('users.branch_id', $criteria['branch_id'] ?? 0);

        return $query->pluck('users.id');
    }

    private function recipientQuery(User $user): Builder
    {
        return match ($user->primaryRole()) {
            'general_manager', 'database_manager' => User::query(),
            'branch_manager' => User::where('branch_id', $user->branch_id),
            default => User::where('department_id', $user->department_id),
        };
    }

    private function visibleFileQuery(User $user): Builder
    {
        $role = $user->primaryRole();

        return DriveFile::query()->where(function (Builder $query) use ($user, $role) {
            $query->where('uploader_id', $user->id)
                ->orWhere(fn (Builder $organizationFiles) => $organizationFiles
                    ->where('scope', 'organization')
                    ->whereHas('uploader', fn (Builder $uploader) => $uploader->where('role', '!=', 'database_manager')))
                ->orWhereIn('folder_id', $this->visibleFolderIds($user))
                ->orWhereHas('sharedUsers', fn (Builder $shares) => $shares->whereKey($user->id))
                ->orWhere(fn (Builder $departmentFiles) => $departmentFiles->whereIn('scope', ['department', 'task'])->where('department_id', $user->department_id));

            if ($role === 'department_head') $query->orWhereHas('uploader', fn (Builder $uploader) => $uploader->where('department_id', $user->department_id));
            if ($role === 'branch_manager') $query->orWhereHas('uploader', fn (Builder $uploader) => $uploader->where('branch_id', $user->branch_id));
            if ($role === 'general_manager') $query->orWhereNotNull('id');
            if ($role === 'database_manager') $query->orWhereHas('uploader', fn (Builder $uploader) => $uploader->whereIn('role', ['employee', 'technician']));
        });
    }
}
