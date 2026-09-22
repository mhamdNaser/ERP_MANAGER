<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/**
 * الاختبار يعمل على مجلد مؤقت لا على قوالب المؤسسة: مسار القالب ومجلد
 * النسخ السابقة كلاهما من الإعدادات، فيُحوَّلان هنا إلى مجلد يُحذف بعدها.
 */
function templateSandbox(): string
{
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cnd-templates-' . uniqid();
    mkdir($directory . DIRECTORY_SEPARATOR . 'blank', 0775, true);

    $filled = resource_path('templates/documents/hr/leave-request.docx');
    $blank = resource_path('templates/documents/hr/blank/leave-request.docx');

    copy($filled, $directory . DIRECTORY_SEPARATOR . 'leave-request.docx');
    copy($blank, $directory . DIRECTORY_SEPARATOR . 'blank' . DIRECTORY_SEPARATOR . 'leave-request.docx');

    config([
        'document_templates.hr.leave_request' => $directory . DIRECTORY_SEPARATOR . 'leave-request.docx',
        'document_templates.backups_path' => $directory . DIRECTORY_SEPARATOR . 'backups',
    ]);

    return $directory;
}

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    foreach (scandir($directory) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $entry;
        is_dir($path) ? removeDirectory($path) : @unlink($path);
    }

    @rmdir($directory);
}

/** نسخة قابلة للرفع من ملف حقيقي — لأن الرفع ينقل الملف لا ينسخه. */
function uploadableCopy(string $source): UploadedFile
{
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upload-' . uniqid() . '.docx';
    copy($source, $path);

    return new UploadedFile(
        $path,
        'template.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true,
    );
}

function templateManager(string $suffix): User
{
    $user = User::create([
        'name' => 'Database manager',
        'email' => "tpl-manager-{$suffix}@test.local",
        'password' => 'password',
        'role' => 'database_manager',
        'api_token' => hash('sha256', "tpl-manager-{$suffix}"),
    ]);
    $user->assignRole('database_manager');

    return $user;
}

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->sandbox = templateSandbox();
});

afterEach(function () {
    removeDirectory($this->sandbox);
});

it('lists the templates with their fields and file state', function () {
    templateManager('list');

    $response = $this->withToken('tpl-manager-list')->getJson('/api/document-templates')->assertOk();

    $templates = collect($response->json('templates'));
    expect($templates)->not->toBeEmpty();

    $leave = $templates->firstWhere('key', 'hr.leave_request');
    expect($leave['exists'])->toBeTrue()
        ->and($leave['missing'])->toBe([])
        ->and($leave['has_blank'])->toBeTrue()
        ->and($leave['placeholders'])->toContain('employee_name');
});

it('keeps the tab shut to anyone without the permission', function () {
    $user = User::create([
        'name' => 'Employee',
        'email' => 'tpl-employee@test.local',
        'password' => 'password',
        'role' => 'employee',
        'api_token' => hash('sha256', 'tpl-employee'),
    ]);
    $user->assignRole('employee');

    $this->withToken('tpl-employee')->getJson('/api/document-templates')->assertForbidden();
});

it('replaces a template in place and keeps the previous version', function () {
    templateManager('replace');
    $path = config('document_templates.hr.leave_request');
    $before = md5_file($path);

    $this->withToken('tpl-manager-replace')
        ->post('/api/document-templates/hr.leave_request', [
            'template' => uploadableCopy(resource_path('templates/documents/hr/request-approval.docx')),
            'force' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('template.key', 'hr.leave_request');

    // الملف الجديد حلّ محل القديم بالاسم والمسار نفسيهما.
    expect(basename($path))->toBe('leave-request.docx')
        ->and(md5_file($path))->not->toBe($before);

    $leave = collect($this->withToken('tpl-manager-replace')->getJson('/api/document-templates')->json('templates'))
        ->firstWhere('key', 'hr.leave_request');

    expect($leave['backups'])->toHaveCount(1)
        ->and(md5_file(config('document_templates.backups_path') . '/hr/leave_request/' . $leave['backups'][0]['name']))
        ->toBe($before);
});

it('refuses a template that is missing fields unless the upload insists', function () {
    templateManager('missing');
    $blank = resource_path('templates/documents/hr/blank/leave-request.docx');
    $path = config('document_templates.hr.leave_request');
    $before = md5_file($path);

    $this->withToken('tpl-manager-missing')
        ->post('/api/document-templates/hr.leave_request', ['template' => uploadableCopy($blank)])
        ->assertStatus(422)
        ->assertJsonPath('requires_force', true)
        ->assertJsonFragment(['missing' => [
            'registry_number', 'gregorian_date', 'hijri_date',
            'employee_name', 'job_title', 'employee_number',
            'days', 'reason', 'start_date', 'end_date',
        ]]);

    // الرفض لا يلمس الملف.
    expect(md5_file($path))->toBe($before);

    $this->withToken('tpl-manager-missing')
        ->post('/api/document-templates/hr.leave_request', [
            'template' => uploadableCopy($blank),
            'force' => 1,
        ])
        ->assertOk();

    expect(md5_file($path))->toBe(md5_file($blank));
});

it('restores a previous version and keeps the replaced one', function () {
    templateManager('restore');
    $path = config('document_templates.hr.leave_request');
    $original = md5_file($path);

    $this->withToken('tpl-manager-restore')
        ->post('/api/document-templates/hr.leave_request', [
            'template' => uploadableCopy(resource_path('templates/documents/hr/blank/leave-request.docx')),
            'force' => 1,
        ])
        ->assertOk();

    $leave = collect($this->withToken('tpl-manager-restore')->getJson('/api/document-templates')->json('templates'))
        ->firstWhere('key', 'hr.leave_request');

    $this->withToken('tpl-manager-restore')
        ->postJson('/api/document-templates/hr.leave_request/restore', ['backup' => $leave['backups'][0]['name']])
        ->assertOk()
        ->assertJsonPath('template.missing', []);

    expect(md5_file($path))->toBe($original);
});

it('rejects a backup name that tries to walk out of its folder', function () {
    templateManager('escape');

    $this->withToken('tpl-manager-escape')
        ->postJson('/api/document-templates/hr.leave_request/restore', ['backup' => '../../../.env'])
        ->assertStatus(500);
});

it('serves the current template and the blank form', function () {
    templateManager('download');

    $this->withToken('tpl-manager-download')
        ->get('/api/document-templates/hr.leave_request/download')
        ->assertOk()
        ->assertDownload('leave-request.docx');

    $this->withToken('tpl-manager-download')
        ->get('/api/document-templates/hr.leave_request/blank')
        ->assertOk()
        ->assertDownload('blank-leave-request.docx');
});
