<?php
namespace App\Modules\Drive\Repositories\Interfaces;

use App\Models\DriveFile;
use App\Models\DriveFolder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

interface DriveRepositoryInterface
{
    /** نطاق الرؤية: ما يملكه المستخدم، وما شورك به، وما يراه بحكم دوره. */
    public function visibleFiles(User $user): Collection;
    public function visibleFolders(User $user): Collection;
    public function visibleFolderIds(User $user): array;
    public function userSeesFile(User $user, DriveFile $file): bool;
    /** ملفات التنزيل المجمَّع: المحدَّدة منها، أو كلها عند تمرير مصفوفة فارغة. */
    public function visibleFilesFor(User $user, array $fileIds, array $folderIds): Collection;

    public function departmentsFor(User $user): Collection;
    public function canAccessDepartment(User $user, int $departmentId): bool;
    public function recipientsFor(User $user): Collection;
    /** المستخدمون المستهدفون بالمشاركة: target، user_ids، department_id، branch_id. */
    public function shareRecipientIds(User $actor, array $criteria): BaseCollection;

    public function findFolder(int $folderId): DriveFolder;
    public function findTask(int $taskId): Task;
    public function fileByToken(string $token): DriveFile;
    public function folderByToken(string $token): DriveFolder;
    /** المجلد وكل ما تحته، لعمليات النشر والحذف والأرشفة. */
    public function descendantFolderIds(DriveFolder $folder, bool $includeSelf = false): array;
    public function foldersIn(array $folderIds): Collection;
    public function filesInFolders(array $folderIds): Collection;
    public function publicFolderListing(DriveFolder $folder, array $folderIds): array;

    public function createFile(array $attributes): DriveFile;
    public function createFolder(array $attributes): DriveFolder;
    public function updateFile(DriveFile $file, array $attributes): DriveFile;
    public function updateFolder(DriveFolder $folder, array $attributes): DriveFolder;
    public function deleteFile(DriveFile $file): void;
    public function deleteFolder(DriveFolder $folder): void;
    public function loadFile(DriveFile $file, bool $withShareCount = false): DriveFile;
    public function loadFileSharing(DriveFile $file): DriveFile;
    public function loadFolder(DriveFolder $folder): DriveFolder;
    public function loadFolderSharing(DriveFolder $folder): DriveFolder;

    public function shareFile(DriveFile $file, BaseCollection $userIds, int $sharedBy): void;
    public function shareFolder(DriveFolder $folder, BaseCollection $userIds, int $sharedBy): void;
    public function revokeFileShare(DriveFile $file, array $criteria): void;
    public function revokeFolderShare(DriveFolder $folder, array $criteria): void;

    public function usedBytes(User $user): int;
    /** سعات الأدوار محفوظة بجدول مستقل قد لا يكون مُرحَّلاً بعد. */
    public function hasRoleQuotaTable(): bool;
    public function roleQuotaMap(): BaseCollection;
    public function roleQuotaBytes(string $role): ?int;
    public function saveRoleQuota(string $role, ?int $quotaBytes): void;
}
