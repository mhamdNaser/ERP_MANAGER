<?php

use App\Models\MaintenanceCategory;
use App\Models\MaintenanceItem;
use App\Models\User;
use Database\Seeders\MaintenanceCatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

/** فني صيانة يملك الإدارة، ومدير عام يطّلع فقط، وموظف لا يرى التبويب. */
function maintenanceFixture(): array
{
    $make = function (string $key, string $role, array $permissions = []) {
        $user = User::create(['name' => $key, 'email' => "mt-{$key}@test.local", 'password' => 'password', 'role' => $role, 'api_token' => hash('sha256', "mt-{$key}")]);
        $user->assignRole($role);
        $user->givePermissionTo($permissions);

        return $user;
    };

    return [
        'technician' => $make('technician', 'technician', ['maintenance.view', 'maintenance.manage']),
        'manager' => $make('manager', 'general_manager'),
        'employee' => $make('employee', 'employee'),
    ];
}

/** ملف بترتيب ملف العناصر في الفرع: ترويسة، عناوين، بيانات، سطر إجمالي، توقيع. */
function branchStyleWorkbook(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['الجمهورية العربية السورية | Syrian Arab Republic'],
        ['إدارة الاتصالات و الشبكات - فرع الصيانة'],
        ['اسم العنصر', 'سيريال العنصر', 'الجهاز', 'صورة القطعة', 'سعر الوحدة ($)', 'العدد', 'التكلفة الإجمالية ($)'],
        ...$rows,
        ['الإجمالي', null, null, null, 99, 999, 9999],
        [null, null, null, null, null, 'مسؤول فرع الصيانة'],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'mt').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'Elements.xlsx', null, null, true);
}

beforeEach(function () {
    Storage::fake('public');
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MaintenanceCatalogSeeder::class);
});

it('walks parts from the warehouse through maintenance and keeps every count explained', function () {
    maintenanceFixture();
    $category = MaintenanceCategory::where('name', 'قطع إلكترونية')->first();

    $item = $this->withToken('mt-technician')->postJson('/api/maintenance/items', [
        'name' => 'IC',
        'part_number' => 'AD8314',
        'category_id' => $category->id,
        'device' => 'RD-980',
        'unit_price' => 6,
        'initial_quantity' => 10,
    ])->assertCreated()
        ->assertJsonPath('code', fn ($code) => str_starts_with($code, 'MNT-'))
        ->assertJsonPath('quantities.in_stock', 10)
        ->json();

    $this->withToken('mt-technician')->postJson("/api/maintenance/items/{$item['id']}/move", [
        'from_status' => 'in_stock', 'to_status' => 'under_maintenance', 'quantity' => 4,
    ])->assertOk()->assertJsonPath('quantities.in_stock', 6)->assertJsonPath('quantities.under_maintenance', 4);

    $this->withToken('mt-technician')->postJson("/api/maintenance/items/{$item['id']}/move", [
        'from_status' => 'under_maintenance', 'to_status' => 'repaired', 'quantity' => 3,
    ])->assertOk();

    $this->withToken('mt-technician')->postJson("/api/maintenance/items/{$item['id']}/move", [
        'from_status' => 'under_maintenance', 'to_status' => 'damaged', 'quantity' => 1,
    ])->assertOk();

    $this->withToken('mt-technician')->postJson("/api/maintenance/items/{$item['id']}/issue", [
        'from_status' => 'repaired', 'quantity' => 2, 'note' => 'تركيب في محطة RD-980',
    ])->assertOk()
        ->assertJsonPath('quantities', ['in_stock' => 6, 'under_maintenance' => 0, 'repaired' => 1, 'ready' => 0, 'damaged' => 1])
        ->assertJsonPath('total_quantity', 8);

    expect(MaintenanceItem::find($item['id'])->movements()->count())->toBe(5);

    $stats = $this->withToken('mt-technician')->getJson('/api/maintenance/statistics')->assertOk()->json();
    expect($stats['totals']['units'])->toBe(8)
        ->and($stats['repair_rate'])->toEqual(75.0)
        ->and(collect($stats['by_status'])->firstWhere('status', 'damaged')['units'])->toBe(1);
});

it('refuses transitions outside the workflow and moving more than is there', function () {
    maintenanceFixture();
    $item = $this->withToken('mt-technician')->postJson('/api/maintenance/items', ['name' => 'شاشة', 'initial_quantity' => 2])->json();

    // التالفة لا تعود إلى الجاهزة إلا عبر الصيانة.
    $this->withToken('mt-technician')->postJson("/api/maintenance/items/{$item['id']}/move", [
        'from_status' => 'in_stock', 'to_status' => 'damaged', 'quantity' => 1,
    ])->assertOk();
    $this->withToken('mt-technician')->postJson("/api/maintenance/items/{$item['id']}/move", [
        'from_status' => 'damaged', 'to_status' => 'ready', 'quantity' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors('to_status');

    $this->withToken('mt-technician')->postJson("/api/maintenance/items/{$item['id']}/move", [
        'from_status' => 'in_stock', 'to_status' => 'ready', 'quantity' => 5,
    ])->assertUnprocessable()->assertJsonValidationErrors('quantity');

    expect(MaintenanceItem::find($item['id'])->quantities)->toMatchArray(['in_stock' => 1, 'damaged' => 1]);
});

it('lets viewers read and export but only managers write', function () {
    maintenanceFixture();

    $this->withToken('mt-manager')->getJson('/api/maintenance/items')->assertOk()->assertJsonPath('can_manage', false);
    $this->withToken('mt-manager')->postJson('/api/maintenance/items', ['name' => 'x'])->assertForbidden();
    $this->withToken('mt-manager')->get('/api/maintenance/export')->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $this->withToken('mt-employee')->getJson('/api/maintenance/items')->assertForbidden();

    $this->withToken('mt-technician')->postJson('/api/maintenance/brands', ['name' => 'Kenwood'])->assertCreated();
    $this->withToken('mt-technician')->postJson('/api/maintenance/brands', ['name' => 'Kenwood'])->assertUnprocessable();
});

it('imports a branch spreadsheet as it is, skipping titles and totals and re-uploads without duplicating', function () {
    maintenanceFixture();
    $category = MaintenanceCategory::where('name', 'قطع إلكترونية')->first();
    $rows = [
        ['ic flash ', 'GL128S10GHIV2', 'كتلة MD785 & RD-980', null, 8, 50, 400],
        ['Ethernet transformer', 'HX1188NL', 'RD-980', '#VALUE!', 6, 50, 300],
        ['transistor', 'Tip122', null, null, 0.5, 20, 10],
    ];

    $preview = $this->withToken('mt-technician')->post('/api/maintenance/imports/preview', [
        'file' => branchStyleWorkbook($rows), 'category_id' => $category->id,
    ])->assertOk()->json();
    expect($preview['summary'])->toMatchArray(['total' => 3, 'create' => 3, 'units' => 120])
        ->and($preview['rows'][0]['name'])->toBe('ic flash');

    $this->withToken('mt-technician')->post('/api/maintenance/imports', [
        'file' => branchStyleWorkbook($rows), 'category_id' => $category->id,
    ])->assertCreated()->assertJsonPath('created_count', 3);

    $this->withToken('mt-technician')->post('/api/maintenance/imports', [
        'file' => branchStyleWorkbook($rows), 'category_id' => $category->id,
    ])->assertCreated()->assertJsonPath('created_count', 0)->assertJsonPath('skipped_count', 3);

    expect(MaintenanceItem::count())->toBe(3)
        ->and(MaintenanceItem::where('part_number', 'HX1188NL')->first()->quantities['in_stock'])->toBe(50);
});

it('reads the tools sheet layout where «النوع» holds the unit and the brand hides in the notes', function () {
    maintenanceFixture();
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->fromArray([
        ['عمود1', 'عمود2', 'عمود22', 'عمود3'],
        ['اسم الأداة', 'العدد', 'النوع', 'ملاحظات', 'سعر الوحدة ($)', 'إجمالي البند ($)'],
        ['عدسة مكبرة ليد عادية', 3, 'قطعة', 'ماركة YAXUN رقم الموديل YX-929', 12, 36],
        ['إجمالي الجدول الأول', 3, null, null, null, 36],
        ['الجمهورية العربية السورية | Syrian Arab Republic'],
        ['اسم الأداة', 'العدد', 'النوع', 'ملاحظات', 'سعر الوحدة ($)', 'إجمالي البند ($)'],
        ['sugon 8560 or Quick 861dw', 3, 'قطعة', 'عبارة عن هيتر ماركة ممتازة', 270, 810],
        ['ماكينة لحام نقطي', 1, 'قطعة', 'sunkko-738 Al', 200, 200],
        ['ماكينة لحام نقطي', 2, 'قطعة', null, 20, 40],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'mt').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $rows = $this->withToken('mt-technician')->post('/api/maintenance/imports/preview', [
        'file' => new UploadedFile($path, 'tools.xlsx', null, null, true),
    ])->assertOk()->json('rows');

    // السطران المتشابهان في الملف نفسه قطعتان مختلفتان، لا تكرار.
    expect($rows)->toHaveCount(4)
        ->and(collect($rows)->pluck('action')->unique()->all())->toBe(['create'])
        ->and($rows[0])->toMatchArray(['unit' => 'قطعة', 'brand' => 'YAXUN', 'part_number' => 'YX-929', 'type' => null])
        ->and($rows[1]['brand'])->toBeNull();
});

it('round-trips its own export back into the statuses it came from', function () {
    maintenanceFixture();
    $item = $this->withToken('mt-technician')->postJson('/api/maintenance/items', ['name' => 'Rotary Encoder', 'initial_quantity' => 5])->json();

    // النوع يُستنتج من الاسم عند الرفع إلى فئة فيها أنواع.
    $category = MaintenanceCategory::where('name', 'قطع إلكترونية')->first();
    $preview = $this->withToken('mt-technician')->post('/api/maintenance/imports/preview', [
        'file' => branchStyleWorkbook([['ic power', 'TPS5450', 'MD785', null, 10, 5, 50]]), 'category_id' => $category->id,
    ])->json('rows');
    expect($preview[0]['type'])->toBe('IC');
    $this->withToken('mt-technician')->postJson("/api/maintenance/items/{$item['id']}/move", [
        'from_status' => 'in_stock', 'to_status' => 'under_maintenance', 'quantity' => 2,
    ])->assertOk();

    $response = $this->withToken('mt-technician')->get('/api/maintenance/export')->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'mt').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $book = IOFactory::load($path);
    expect($book->getSheetNames())->toBe(['القطع', 'الإحصاءات', 'سجل الحركات'])
        // الأصفار تُكتب أصفاراً لا خلايا فارغة.
        ->and($book->getSheet(0)->getCell('M7')->getValue())->toBe(0);

    MaintenanceItem::query()->delete();
    $this->withToken('mt-technician')->post('/api/maintenance/imports', [
        'file' => new UploadedFile($path, 'export.xlsx', null, null, true),
    ])->assertCreated()->assertJsonPath('created_count', 1);

    expect(MaintenanceItem::first()->quantities)->toMatchArray(['in_stock' => 3, 'under_maintenance' => 2]);
});

it('restricts a sidebar tab to the audience chosen for it — branch, department, person or everyone, combined', function () {
    maintenanceFixture();
    $admin = User::create(['name' => 'admin', 'email' => 'mt-admin@test.local', 'password' => 'password', 'role' => 'database_manager', 'api_token' => hash('sha256', 'mt-admin')]);
    $admin->assignRole('database_manager');
    $branch = \App\Models\Branch::create(['name' => 'فرع الصيانة', 'code' => 'M-T']);
    $otherBranch = \App\Models\Branch::create(['name' => 'فرع العمليات', 'code' => 'OP-T']);
    $studies = \App\Models\Department::create(['name' => 'قسم الدراسات', 'code' => 'ST-T', 'branch_id' => $otherBranch->id]);
    $make = function (string $key, array $attributes) {
        $user = User::create(['name' => $key, 'email' => "mt-{$key}@test.local", 'password' => 'password', 'role' => 'employee', 'api_token' => hash('sha256', "mt-{$key}"), ...$attributes]);
        $user->assignRole('employee');

        return $user;
    };
    $member = $make('member', ['branch_id' => $branch->id]);
    $analyst = $make('analyst', ['branch_id' => $otherBranch->id, 'department_id' => $studies->id]);
    $outsider = $make('outsider', ['branch_id' => $otherBranch->id]);
    $link = fn (array $target, string $level = 'view') => $this->withToken('mt-admin')->postJson('/api/tab-access', ['tab' => 'maintenance', 'level' => $level, ...$target]);

    // بلا روابط: كما اليوم — المدير العام يرى بدوره، والموظف لا.
    $this->withToken('mt-manager')->getJson('/api/maintenance/items')->assertOk();
    $this->withToken('mt-member')->getJson('/api/maintenance/items')->assertForbidden();
    expect((array) $this->withToken('mt-member')->getJson('/api/auth/me')->json('tab_visibility'))->toBe([]);

    // فرع كامل بمستوى إدارة + قسم داخل فرع آخر + لا شيء غيرهما.
    $link(['branch_id' => $branch->id], 'manage')->assertCreated()->assertJsonPath('branch.name', 'فرع الصيانة');
    $link(['department_id' => $studies->id])->assertCreated();
    $this->withToken('mt-member')->postJson('/api/maintenance/items', ['name' => 'x'])->assertCreated();
    $this->withToken('mt-analyst')->getJson('/api/maintenance/items')->assertOk()->assertJsonPath('can_manage', false);
    $this->withToken('mt-outsider')->getJson('/api/maintenance/items')->assertForbidden();
    expect($this->withToken('mt-member')->getJson('/api/auth/me')->json('tab_visibility.maintenance'))->toBeTrue()
        ->and($this->withToken('mt-outsider')->getJson('/api/auth/me')->json('tab_visibility.maintenance'))->toBeFalse();

    // صار التبويب مقصوراً: المدير العام خارج الجمهور يُحجب عنه رغم دوره...
    $this->withToken('mt-manager')->getJson('/api/maintenance/items')->assertForbidden();
    expect($this->withToken('mt-manager')->getJson('/api/auth/me')->json('permissions'))->not->toContain('maintenance.view');
    // ...حتى يُضاف شخصياً. ومدير قواعد البيانات يرى دائماً.
    $link(['user_id' => User::where('email', 'mt-manager@test.local')->value('id')])->assertCreated();
    $this->withToken('mt-manager')->getJson('/api/maintenance/items')->assertOk();
    $this->withToken('mt-admin')->getJson('/api/maintenance/items')->assertOk();
    // صلاحية الإدارة الممنوحة مباشرةً تُحجب أيضاً عمّن صار خارج الجمهور.
    $this->withToken('mt-technician')->getJson('/api/maintenance/items')->assertForbidden();

    // «الجميع» يفتحه لكل أحد، ويبقى مستوى الإدارة لفرعه.
    $link(['everyone' => true])->assertCreated()->assertJsonPath('everyone', true);
    $this->withToken('mt-outsider')->getJson('/api/maintenance/items')->assertOk()->assertJsonPath('can_manage', false);
    $this->withToken('mt-member')->getJson('/api/maintenance/items')->assertJsonPath('can_manage', true);

    // الربط الواحد لهدف واحد، ولا يتكرر، وتبويب الدور لا يُمنح بمستوى إدارة.
    $link(['branch_id' => $branch->id, 'user_id' => $member->id])->assertUnprocessable();
    $link([])->assertUnprocessable();
    $link(['branch_id' => $branch->id], 'manage');
    expect(\App\Models\TabAccessGrant::where('tab', 'maintenance')->whereNotNull('branch_id')->count())->toBe(1);
    $this->withToken('mt-admin')->postJson('/api/tab-access', ['tab' => 'employees', 'everyone' => true, 'level' => 'manage'])->assertJsonPath('level', 'view');
    $this->withToken('mt-admin')->postJson('/api/tab-access', ['tab' => 'permissions', 'everyone' => true])->assertUnprocessable();

    // إزالة كل الروابط تعيد التبويب إلى حكم الأدوار.
    \App\Models\TabAccessGrant::where('tab', 'maintenance')->get()->each(fn ($grant) => $this->withToken('mt-admin')->deleteJson("/api/tab-access/{$grant->id}")->assertOk());
    $this->withToken('mt-technician')->getJson('/api/maintenance/items')->assertOk();
    $this->withToken('mt-outsider')->getJson('/api/maintenance/items')->assertForbidden();

    // إدارة الجمهور لمن يدير الصلاحيات فقط.
    $this->withToken('mt-member')->getJson('/api/tab-access')->assertForbidden();
});

it('deletes an uploaded file without touching the parts it brought in', function () {
    maintenanceFixture();
    $import = $this->withToken('mt-technician')->post('/api/maintenance/imports', [
        'file' => branchStyleWorkbook([['IC', 'LM124', 'RD-980', null, 2, 50, 100]]),
    ])->assertCreated()->json();
    $item = MaintenanceItem::first();
    expect($item->import_id)->toBe($import['id']);
    Storage::disk('public')->assertExists($import['file_path']);

    $this->withToken('mt-manager')->deleteJson("/api/maintenance/imports/{$import['id']}")->assertForbidden();
    $this->withToken('mt-technician')->deleteJson("/api/maintenance/imports/{$import['id']}")->assertOk();

    Storage::disk('public')->assertMissing($import['file_path']);
    expect(\App\Models\MaintenanceImport::count())->toBe(0)
        ->and($item->fresh()->import_id)->toBeNull()
        ->and($item->fresh()->quantities['in_stock'])->toBe(50)
        ->and($item->movements()->count())->toBe(1);
});
