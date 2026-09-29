<?php

use App\Modules\Database\Repositories\Interfaces\DatabaseRepositoryInterface;
use App\Modules\Database\Services\DatabaseTableRegistry;
use App\Modules\Database\Services\MigrationPackageService;
use App\Modules\Database\Services\PgDumpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * pg_dump أداةٌ خارجية تحتاج PostgreSQL، والاختبارات على SQLite — فيُستبدل
 * بمزيّف يكتب ملفاً. ما يُختبر هنا هو ما تضيفه الحزمة فوق النسخة المعتادة:
 * شجرة التخزين والقوالب والبطاقة، لا أداة postgres نفسها.
 */
class FakePgDump extends PgDumpService
{
    public function __construct() {}

    public function dump(string $absolutePath, string $format, ?array $tables, string $connection): void
    {
        file_put_contents($absolutePath, 'FAKE-DUMP-CONTENT');
    }
}

function packageRoots(): array
{
    $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cnd-package-' . uniqid();

    // شجرة تخزين فيها ملفٌ عادي، ونسخة احتياطية سابقة، وملف مؤقت.
    mkdir($base . '/storage/public/drive', 0775, true);
    mkdir($base . '/storage/private/database-backups', 0775, true);
    mkdir($base . '/storage/private/tmp', 0775, true);
    mkdir($base . '/templates/documents/hr', 0775, true);

    file_put_contents($base . '/storage/public/drive/report.pdf', 'ملف حقيقي');
    file_put_contents($base . '/storage/private/database-backups/old-backup.zip', str_repeat('x', 500));
    file_put_contents($base . '/storage/private/tmp/scratch.tmp', 'مؤقت');
    file_put_contents($base . '/templates/documents/hr/leave.docx', 'قالب');

    return ['base' => $base, 'storage' => $base . '/storage', 'templates' => $base . '/templates'];
}

function readArchive(string $path): array
{
    $zip = new ZipArchive();
    expect($zip->open($path))->toBeTrue();

    $entries = [];
    for ($index = 0; $index < $zip->numFiles; $index += 1) {
        $entries[] = $zip->getNameIndex($index);
    }

    $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
    $readme = (string) $zip->getFromName('README.txt');
    $zip->close();

    return compact('entries', 'manifest', 'readme');
}

function buildPackage(array $roots): array
{
    $service = new MigrationPackageService(
        app(DatabaseTableRegistry::class),
        new FakePgDump(),
        app(DatabaseRepositoryInterface::class),
        $roots['storage'],
        $roots['templates'],
    );

    return $service->build('test');
}

beforeEach(function () {
    Storage::fake('local');
    $this->roots = packageRoots();
});

it('carries the database, the whole storage tree and the templates', function () {
    $package = buildPackage($this->roots);
    $archive = readArchive(Storage::disk('local')->path($package['path']));

    expect($archive['entries'])->toContain('database.backup')
        ->and($archive['entries'])->toContain('manifest.json')
        ->and($archive['entries'])->toContain('README.txt')
        ->and($archive['entries'])->toContain('storage/public/drive/report.pdf')
        // القوالب لا يشير إليها أي صف في القاعدة، فهي بالضبط ما تفوّته النسخة المعتادة.
        ->and($archive['entries'])->toContain('templates/documents/hr/leave.docx');
});

it('leaves previous backups and scratch files out of the package', function () {
    $package = buildPackage($this->roots);
    $archive = readArchive(Storage::disk('local')->path($package['path']));

    $unwanted = array_filter(
        $archive['entries'],
        fn ($entry) => str_contains($entry, 'database-backups') || str_contains($entry, 'scratch.tmp'),
    );

    expect($unwanted)->toBeEmpty()
        ->and($archive['manifest']['storage']['files'])->toBe(1)
        ->and($archive['manifest']['storage']['skipped'])->toBe(2);
});

it('stamps the package with a verifiable manifest', function () {
    $package = buildPackage($this->roots);
    $archive = readArchive(Storage::disk('local')->path($package['path']));
    $manifest = $archive['manifest'];

    expect($manifest['app_version'])->not->toBeEmpty()
        ->and($manifest['database']['dump']['sha256'])->toBe(hash('sha256', 'FAKE-DUMP-CONTENT'))
        ->and($manifest['database']['migrations'])->toHaveKeys(['count', 'latest'])
        ->and($manifest['database']['tables'])->toHaveKey('users')
        ->and($manifest['templates']['files'])->toBe(1);

    // البصمة داخل التعليمات كي يتحقق منها من يستعيد قبل أن يشغّل pg_restore.
    expect($archive['readme'])->toContain($manifest['database']['dump']['sha256'])
        ->and($archive['readme'])->toContain('pg_restore');
});
