<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\DriveFile;
use App\Models\Task;
use App\Models\User;
use App\Modules\Database\Services\BackupPresetRegistry;
use App\Modules\Database\Services\DatabaseBackupService;
use App\Modules\Database\Services\DatabaseRestoreService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * الحزم الجاهزة تُختبر على قرص وهمي: النسخة تُكتب في مجلد اختباري لا في
 * نسخ المؤسسة، وملفات الدرايف تُزرع في قرص public الوهمي.
 */
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->seed(RolePermissionSeeder::class);
});

function presetActor(): User
{
    $user = User::create([
        'name' => 'Backup actor',
        'email' => 'preset@test.local',
        'password' => 'password',
        'role' => 'database_manager',
        'api_token' => hash('sha256', 'preset-actor'),
    ]);
    $user->assignRole('database_manager');
    $user->givePermissionTo(['database.backups.manage', 'database.maintenance.manage']);

    return $user;
}

/** مهمة واحدة بملفها، وملفٌ شخصي لا علاقة له بالمهام. */
function seedTaskWithFile(User $owner): array
{
    $branch = Branch::create(['name' => 'فرع الاختبار', 'code' => 'TB']);
    $department = Department::create(['name' => 'قسم الاختبار', 'code' => 'TD', 'branch_id' => $branch->id]);

    $task = Task::create([
        'department_id' => $department->id,
        'creator_id' => $owner->id,
        'assignee_id' => $owner->id,
        'title' => 'مهمة بملف',
        'description' => 'وصف',
    ]);

    Storage::disk('public')->put('drive/task-file.pdf', 'محتوى ملف المهمة');
    Storage::disk('public')->put('drive/private-file.pdf', 'ملف شخصي لا علاقة له');

    $taskFile = DriveFile::create([
        'uploader_id' => $owner->id,
        'task_id' => $task->id,
        'scope' => 'task',
        'name' => 'task-file.pdf',
        'path' => 'drive/task-file.pdf',
        'size' => 20,
        'mime_type' => 'application/pdf',
    ]);

    $personalFile = DriveFile::create([
        'uploader_id' => $owner->id,
        'scope' => 'personal',
        'name' => 'private-file.pdf',
        'path' => 'drive/private-file.pdf',
        'size' => 20,
        'mime_type' => 'application/pdf',
    ]);

    return compact('task', 'taskFile', 'personalFile');
}

/** يقرأ محتويات أرشيف النسخة. */
function archiveEntries(string $relativePath): array
{
    $zip = new ZipArchive();
    expect($zip->open(Storage::disk('local')->path($relativePath)))->toBeTrue();

    $entries = [];
    for ($index = 0; $index < $zip->numFiles; $index += 1) {
        $entries[] = $zip->getNameIndex($index);
    }

    $dump = $zip->getFromName('data.json');
    $zip->close();

    return ['entries' => $entries, 'dump' => json_decode((string) $dump, true)];
}

it('lists the presets the screen can offer', function () {
    $presets = collect(app(BackupPresetRegistry::class)->all());

    expect($presets->pluck('key'))->toContain('tasks', 'formal_correspondences', 'hr', 'fleet')
        ->and($presets->firstWhere('key', 'tasks')['tables'])->toContain('tasks', 'drive_files');
});

it('pulls a task with its own files and leaves the rest of the drive behind', function () {
    $owner = presetActor();
    $seeded = seedTaskWithFile($owner);

    $presets = app(BackupPresetRegistry::class);
    $backup = app(DatabaseBackupService::class)->createBackup(
        'internal', 'json', $presets->tables('tasks'), true, $presets->rowFilters('tasks'),
    );

    $archive = archiveEntries('database-backups/' . $backup['file_name']);

    // ملف المهمة وحده داخل الأرشيف.
    $files = array_values(array_filter($archive['entries'], fn ($entry) => str_starts_with($entry, 'files/')));
    expect($files)->toHaveCount(1)
        ->and($files[0])->toContain('task-file.pdf');

    // وصفوف الدرايف في النسخة مقصورة على صفوف المهام.
    $driveRows = $archive['dump']['tables']['drive_files'];
    expect($driveRows)->toHaveCount(1)
        ->and($driveRows[0]['id'])->toBe($seeded['taskFile']->id)
        ->and($archive['dump']['tables']['tasks'])->toHaveCount(1);
});

it('refuses a preset in a format that cannot filter rows', function () {
    presetActor();

    app(DatabaseBackupService::class)->createBackup(
        'internal', 'sql', ['tasks'], true, ['drive_files' => ['where_not_null' => ['task_id']]],
    );
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);

it('serves the presets over the API and builds one', function () {
    $owner = presetActor();
    seedTaskWithFile($owner);

    $this->withToken('preset-actor')->getJson('/api/database-backups/presets')
        ->assertOk()
        ->assertJsonFragment(['key' => 'tasks']);

    $this->withToken('preset-actor')
        ->postJson('/api/database-backups/internal', ['preset' => 'tasks'])
        ->assertCreated()
        ->assertJsonPath('preset', 'tasks')
        ->assertJsonPath('bundled', true)
        ->assertJsonPath('files.bundled', 1);
});

it('rejects an unknown preset', function () {
    presetActor();

    $this->withToken('preset-actor')
        ->postJson('/api/database-backups/internal', ['preset' => 'nothing-like-this'])
        ->assertStatus(422);
});

it('restores a task package without touching the rest of the drive', function () {
    $owner = presetActor();
    $seeded = seedTaskWithFile($owner);

    $presets = app(BackupPresetRegistry::class);
    $backup = app(DatabaseBackupService::class)->createBackup(
        'internal', 'json', $presets->tables('tasks'), true, $presets->rowFilters('tasks'),
    );

    // ملف شخصي يُرفع بعد النسخة: لا تعرفه الحزمة، فيجب أن ينجو من استعادتها.
    $laterFile = DriveFile::create([
        'uploader_id' => $owner->id,
        'scope' => 'personal',
        'name' => 'later.pdf',
        'path' => 'drive/later.pdf',
        'size' => 5,
        'mime_type' => 'application/pdf',
    ]);

    app(DatabaseRestoreService::class)->restore($backup['file_name'], $presets->tables('tasks'));

    expect(DriveFile::pluck('id')->sort()->values()->all())
        ->toBe(collect([$seeded['taskFile']->id, $seeded['personalFile']->id, $laterFile->id])->sort()->values()->all())
        ->and(Task::count())->toBe(1);
});
