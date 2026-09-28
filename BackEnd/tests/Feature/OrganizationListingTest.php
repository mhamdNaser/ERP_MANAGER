<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** مدير قواعد بيانات: نطاقه المؤسسة كلها، فيرى كل ما يُنشأ أدناه. */
function listingActor(string $suffix): User
{
    $user = User::create([
        'name' => 'List actor',
        'email' => "listing-{$suffix}@test.local",
        'password' => 'password',
        'role' => 'database_manager',
        'api_token' => hash('sha256', "listing-{$suffix}"),
    ]);
    $user->assignRole('database_manager');
    $user->givePermissionTo(['organization.view', 'employees.view']);

    return $user;
}

function seedOrganization(): array
{
    $networks = Branch::create(['name' => 'فرع الشبكات', 'code' => 'NB']);
    $operations = Branch::create(['name' => 'فرع العمليات', 'code' => 'OP26']);

    $departments = [];
    foreach (['مراقبة الشبكة', 'هندسة الشبكات', 'الكميرات', 'أنظمة الحماية', 'نظم البيانات الجغرافية'] as $index => $name) {
        $departments[] = Department::create([
            'name' => $name,
            'code' => 'NB-' . $index,
            'branch_id' => $networks->id,
        ]);
    }

    $departments[] = Department::create([
        'name' => 'قسم الدراسات',
        'code' => 'OP-1',
        'branch_id' => $operations->id,
    ]);

    return ['networks' => $networks, 'operations' => $operations, 'departments' => $departments];
}

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('keeps the full lists untouched for the editor dropdowns', function () {
    listingActor('full');
    seedOrganization();

    $response = $this->withToken('listing-full')->getJson('/api/organization')->assertOk();

    expect($response->json('branches'))->toHaveCount(2)
        ->and($response->json('departments'))->toHaveCount(6);
});

it('serves branches a page at a time', function () {
    listingActor('branches');
    seedOrganization();

    $this->withToken('listing-branches')->getJson('/api/organization/branches?per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.current_page', 1);

    $this->withToken('listing-branches')->getJson('/api/organization/branches?per_page=1&page=2')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonCount(1, 'data');
});

it('finds a branch by name or by code whatever the letter case', function () {
    listingActor('search');
    seedOrganization();

    $this->withToken('listing-search')->getJson('/api/organization/branches?per_page=5&search=الشبكات')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'NB');

    // الرمز OP26 يُكتب بأحرف صغيرة — والبحث لا يجوز أن يبالي.
    $this->withToken('listing-search')->getJson('/api/organization/branches?per_page=5&search=op26')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'OP26');
});

it('finds departments through the name of their branch', function () {
    listingActor('dept');
    seedOrganization();

    $response = $this->withToken('listing-dept')
        ->getJson('/api/organization/departments?per_page=10&search=العمليات')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('قسم الدراسات');
});

it('returns every employee when no page size is asked for', function () {
    $actor = listingActor('all');
    $organization = seedOrganization();

    foreach (range(1, 6) as $index) {
        User::create([
            'name' => "موظف {$index}",
            'email' => "staff-{$index}@test.local",
            'password' => 'password',
            'role' => 'employee',
            'branch_id' => $organization['networks']->id,
        ]);
    }

    $response = $this->withToken('listing-all')->getJson('/api/employees')->assertOk();

    // ستة موظفين + صاحب الطلب نفسه.
    expect($response->json('employees'))->toHaveCount(7)
        ->and($response->json('meta'))->toBeNull()
        ->and($actor->fresh())->not->toBeNull();
});

it('pages and filters employees on the server', function () {
    listingActor('filters');
    $organization = seedOrganization();

    User::create(['name' => 'سامر المثبت', 'email' => 'fixed@test.local', 'password' => 'password',
        'role' => 'technician', 'employment_type' => 'fixed', 'branch_id' => $organization['networks']->id]);
    User::create(['name' => 'وائل المتعاقد', 'email' => 'contract@test.local', 'password' => 'password',
        'role' => 'employee', 'employment_type' => 'contract', 'branch_id' => $organization['networks']->id]);
    User::create(['name' => 'ليلى العمليات', 'email' => 'ops@test.local', 'password' => 'password',
        'role' => 'employee', 'employment_type' => 'contract', 'branch_id' => $organization['operations']->id]);
    User::create(['name' => 'موظف موقوف', 'email' => 'paused@test.local', 'password' => 'password',
        'role' => 'employee', 'is_active' => false, 'branch_id' => $organization['networks']->id]);

    $this->withToken('listing-filters')->getJson('/api/employees?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'employees')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5);

    $this->withToken('listing-filters')
        ->getJson('/api/employees?per_page=10&branch_id=' . $organization['operations']->id)
        ->assertOk()
        ->assertJsonCount(1, 'employees')
        ->assertJsonPath('employees.0.name', 'ليلى العمليات');

    $this->withToken('listing-filters')->getJson('/api/employees?per_page=10&employment_type=fixed')
        ->assertOk()
        ->assertJsonCount(1, 'employees')
        ->assertJsonPath('employees.0.name', 'سامر المثبت');

    $this->withToken('listing-filters')->getJson('/api/employees?per_page=10&role=technician')
        ->assertOk()
        ->assertJsonCount(1, 'employees');

    $this->withToken('listing-filters')->getJson('/api/employees?per_page=10&status=inactive')
        ->assertOk()
        ->assertJsonCount(1, 'employees')
        ->assertJsonPath('employees.0.name', 'موظف موقوف');

    $this->withToken('listing-filters')->getJson('/api/employees?per_page=10&search=المتعاقد')
        ->assertOk()
        ->assertJsonCount(1, 'employees')
        ->assertJsonPath('employees.0.email', 'contract@test.local');
});

/** حدٌّ أعلى لحجم الصفحة: طلبٌ بلا حدّ يجرّ الجدول كاملاً. */
it('caps how large a page a client may ask for', function () {
    listingActor('cap');
    seedOrganization();

    $this->withToken('listing-cap')->getJson('/api/organization/branches?per_page=5000')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});
