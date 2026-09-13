<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\Report;
use App\Models\CndNotification;
use App\Models\Office;
use App\Models\CustomForm;
use App\Models\CustomFormPublication;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

it('reports system and database readiness before login', function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->getJson('/api/system/health')
        ->assertOk()
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('database', 'متصلة')
        ->assertJsonPath('storage.local.writable', true)
        ->assertJsonPath('storage.public.writable', true)
        ->assertJsonStructure(['upload_limits' => ['upload_max_filesize', 'post_max_size', 'max_file_uploads']]);
});

it('returns continuous dashboard report and task timelines', function () {
    $this->seed(RolePermissionSeeder::class);
    $manager=User::create(['name'=>'Timeline manager','email'=>'timeline@test.local','password'=>'password','role'=>'general_manager','api_token'=>hash('sha256','timeline-token')]);$manager->assignRole('general_manager');
    $this->withToken('timeline-token')->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonCount(6,'analytics.report_trend')
        ->assertJsonCount(8,'analytics.task_trend')
        ->assertJsonStructure(['analytics'=>['report_trend'=>[['key','label','total','completed']],'task_trend'=>[['key','label','created','completed']]]]);
});

it('returns translations and localized api messages', function () {
    $this->getJson('/api/locale/en')->assertOk()->assertJsonPath('Dashboard', 'Dashboard');
    $this->withHeader('X-Language', 'en')->postJson('/api/auth/login', ['email' => 'missing@example.com', 'password' => 'invalid'])
        ->assertUnprocessable()->assertJsonPath('message', 'The supplied credentials are incorrect.');
});

it('allows database managers to browse reports and create backups', function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
    $branch = Branch::create(['name' => 'فرع الاختبار', 'code' => 'TST']);
    $department = Department::create(['name' => 'قسم الاختبار', 'code' => 'TST-D', 'branch_id' => $branch->id]);
    $employee = User::create(['name' => 'موظف', 'email' => 'employee@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $department->id]);
    $manager = User::create(['name' => 'مدير البيانات', 'email' => 'db@test.local', 'password' => 'password', 'role' => 'database_manager', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'test-token')]);
    $manager->assignRole('database_manager');
    Report::create(['employee_id' => $employee->id, 'branch_id' => $branch->id, 'department_id' => $department->id, 'type' => 'daily_report', 'period_start' => now(), 'title' => 'سري', 'summary' => 'سري']);

    $this->withToken('test-token')->getJson('/api/reports')->assertOk()->assertJsonCount(1);
    $this->withToken('test-token')->postJson('/api/database-backups/internal')->assertCreated()->assertJsonPath('kind', 'internal');
    $this->withToken('test-token')->getJson('/api/database-backups')->assertOk()->assertJsonFragment(['kind' => 'internal']);
    $this->withToken('test-token')->getJson('/api/employees')->assertOk()->assertJsonCount(2, 'employees');
});

it('allows database managers to build forms and managers to publish them', function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
    $branch = Branch::create(['name' => 'Branch', 'code' => 'FORM-BR']);
    $department = Department::create(['name' => 'Department', 'code' => 'FORM-DP', 'branch_id' => $branch->id]);
    $dbManager = User::create(['name' => 'DB', 'email' => 'forms-db@test.local', 'password' => 'password', 'role' => 'database_manager', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'forms-db')]); $dbManager->assignRole('database_manager');
    $manager = User::create(['name' => 'Head', 'email' => 'forms-head@test.local', 'password' => 'password', 'role' => 'department_head', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'forms-head')]); $manager->assignRole('department_head');
    $employee = User::create(['name' => 'Employee', 'email' => 'forms-employee@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'forms-employee')]); $employee->assignRole('employee');

    $form = $this->withToken('forms-db')->postJson('/api/forms', [
        'title' => 'استبيان الموظفين',
        'description' => 'نموذج داخلي',
        'target_group' => 'employees',
        'default_scope' => 'department',
        'default_duration_days' => 5,
        'fields' => [
            ['label' => 'الاسم', 'input_type' => 'text', 'is_required' => true],
            ['label' => 'الرضا', 'input_type' => 'select', 'is_required' => true, 'options' => [['value' => '1', 'label' => '1'], ['value' => '2', 'label' => '2']]],
        ],
    ])->assertCreated()->json();

    $publication = $this->withToken('forms-head')->postJson("/api/forms/{$form['id']}/publish", [
        'scope' => 'department',
        'target_group' => 'employees',
        'duration_days' => 3,
        'message' => 'يرجى التعبئة',
    ])->assertCreated()->json();

    $this->assertDatabaseHas('custom_form_publications', ['id' => $publication['id'], 'custom_form_id' => $form['id']]);
    $this->assertDatabaseHas('cnd_notifications', ['custom_form_id' => $form['id'], 'custom_form_publication_id' => $publication['id'], 'user_id' => $employee->id]);

    $this->withToken('forms-employee')->postJson("/api/forms/publications/{$publication['id']}/submit", [
        'payload' => ['field_1' => 'اختبار', 'field_2' => '2'],
    ])->assertCreated()->assertJsonPath('version', 1);

    $this->withToken('forms-employee')->postJson("/api/forms/publications/{$publication['id']}/submit", [
        'payload' => ['field_1' => 'تحديث', 'field_2' => '1'],
    ])->assertCreated()->assertJsonPath('version', 2);

    $this->withToken('forms-employee')->getJson("/api/forms/users/{$employee->id}")->assertOk()->assertJsonCount(2);
});

it('allows employees to submit their own draft to the department head', function () {
    $this->seed(RolePermissionSeeder::class);
    $branch = Branch::create(['name' => 'فرع الاختبار', 'code' => 'TST']);
    $department = Department::create(['name' => 'قسم الاختبار', 'code' => 'TST-D', 'branch_id' => $branch->id]);
    $employee = User::create(['name' => 'موظف', 'email' => 'employee@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'employee-token')]);
    $employee->assignRole('employee');
    $head = User::create(['name' => 'رئيس القسم', 'email' => 'head@test.local', 'password' => 'password', 'role' => 'department_head', 'branch_id' => $branch->id, 'department_id' => $department->id]);
    $head->assignRole('department_head');
    $report = Report::create(['employee_id' => $employee->id, 'branch_id' => $branch->id, 'department_id' => $department->id, 'type' => 'daily_report', 'period_start' => now(), 'title' => 'تقرير', 'summary' => 'ملخص']);

    $this->withToken('employee-token')->postJson("/api/reports/{$report->id}/transition", ['action' => 'submit'])
        ->assertOk()->assertJsonPath('status', 'department_review');

    $this->assertDatabaseHas('cnd_notifications', ['report_id' => $report->id]);
});

it('limits branch managers to departments and employees in their branch', function () {
    $this->seed(RolePermissionSeeder::class);
    $branchA = Branch::create(['name' => 'A', 'code' => 'A']);
    $branchB = Branch::create(['name' => 'B', 'code' => 'B']);
    $departmentA = Department::create(['name' => 'A1', 'code' => 'A1', 'branch_id' => $branchA->id]);
    $departmentB = Department::create(['name' => 'B1', 'code' => 'B1', 'branch_id' => $branchB->id]);
    $manager = User::create(['name' => 'Manager', 'email' => 'manager@test.local', 'password' => 'password', 'role' => 'branch_manager', 'branch_id' => $branchA->id, 'department_id' => $departmentA->id, 'api_token' => hash('sha256', 'manager-token')]);
    $manager->assignRole('branch_manager');
    User::create(['name' => 'Other employee', 'email' => 'other@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branchB->id, 'department_id' => $departmentB->id]);

    $this->withToken('manager-token')->getJson('/api/organization')->assertOk()->assertJsonCount(1, 'branches')->assertJsonCount(1, 'departments');
    $this->withToken('manager-token')->postJson('/api/departments', ['name' => 'Forbidden', 'code' => 'FORBIDDEN', 'branch_id' => $branchB->id])->assertForbidden();
    $this->withToken('manager-token')->getJson('/api/employees')->assertOk()->assertJsonCount(1, 'employees');
});

it('allows department heads to update only employees in their department', function () {
    $this->seed(RolePermissionSeeder::class);
    $branch = Branch::create(['name' => 'Branch', 'code' => 'BR']);
    $departmentA = Department::create(['name' => 'A', 'code' => 'DA', 'branch_id' => $branch->id]);
    $departmentB = Department::create(['name' => 'B', 'code' => 'DB', 'branch_id' => $branch->id]);
    $head = User::create(['name' => 'Head', 'email' => 'department-head@test.local', 'password' => 'password', 'role' => 'department_head', 'branch_id' => $branch->id, 'department_id' => $departmentA->id, 'api_token' => hash('sha256', 'head-token')]);
    $head->assignRole('department_head');
    $employeeA = User::create(['name' => 'Employee A', 'email' => 'a@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $departmentA->id]);
    $employeeB = User::create(['name' => 'Employee B', 'email' => 'b@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $departmentB->id]);

    $this->withToken('head-token')->putJson("/api/employees/{$employeeA->id}", ['job_title' => 'Updated'])->assertOk()->assertJsonPath('job_title', 'Updated');
    $this->withToken('head-token')->putJson("/api/employees/{$employeeB->id}", ['job_title' => 'Forbidden'])->assertForbidden();
    $this->withToken('head-token')->putJson("/api/employees/{$employeeA->id}", ['department_id' => $departmentB->id])->assertForbidden();
});

it('allows general managers to manage the complete organization', function () {
    $this->seed(RolePermissionSeeder::class);
    $manager = User::create(['name' => 'General', 'email' => 'general-manager@test.local', 'password' => 'password', 'role' => 'general_manager', 'api_token' => hash('sha256', 'general-token')]);
    $manager->assignRole('general_manager');

    $branch = $this->withToken('general-token')->postJson('/api/branches', ['name' => 'New Branch', 'code' => 'NEW'])
        ->assertCreated()->assertJsonPath('name', 'New Branch')->json();
    $this->withToken('general-token')->postJson('/api/departments', ['name' => 'New Department', 'code' => 'NEW-D', 'branch_id' => $branch['id']])
        ->assertCreated()->assertJsonPath('branch_id', $branch['id']);
});

it('stores optional employee details in separate tables', function () {
    $this->seed(RolePermissionSeeder::class);
    $branch = Branch::create(['name' => 'Branch', 'code' => 'PROFILE-BR']);
    $department = Department::create(['name' => 'Department', 'code' => 'PROFILE-DP', 'branch_id' => $branch->id]);
    $manager = User::create(['name' => 'DB Manager', 'email' => 'profiles@test.local', 'password' => 'password', 'role' => 'database_manager', 'api_token' => hash('sha256', 'profiles-token')]);
    $manager->assignRole('database_manager');

    $response = $this->withToken('profiles-token')->postJson('/api/employees', [
        'name'=>'Profile User','email'=>'profile-user@test.local','password'=>'password','role'=>'employee',
        'branch_id'=>$branch->id,'department_id'=>$department->id,
        'address'=>['city'=>'Damascus'],
        'personal_details'=>['height_cm'=>180,'weight_kg'=>82,'shoe_size'=>'44','shirt_size'=>'XL'],
    ])->assertCreated();

    $userId = $response->json('id');
    $this->assertDatabaseHas('user_addresses', ['user_id'=>$userId,'city'=>'Damascus']);
    $this->assertDatabaseHas('user_personal_details', ['user_id'=>$userId,'height_cm'=>180]);
    $this->assertDatabaseHas('user_personal_details', ['user_id'=>$userId,'shoe_size'=>'44']);
});

it('allows database managers to configure role permissions', function () {
    $this->seed(RolePermissionSeeder::class);
    $manager = User::create(['name' => 'DB Manager', 'email' => 'permissions@test.local', 'password' => 'password', 'role' => 'database_manager', 'api_token' => hash('sha256', 'permissions-token')]);
    $manager->assignRole('database_manager');
    $employeeRole = \Spatie\Permission\Models\Role::findByName('employee');

    $this->withToken('permissions-token')->getJson('/api/roles-permissions')->assertOk();
    $this->withToken('permissions-token')->putJson("/api/roles-permissions/{$employeeRole->id}", ['permissions' => ['reports.view']])
        ->assertOk()->assertJsonPath('permissions.0.name', 'reports.view');
});

it('opens only the current users notification and includes its report', function () {
    $this->seed(RolePermissionSeeder::class);
    $branch = Branch::create(['name' => 'Branch', 'code' => 'NOT-BR']);
    $department = Department::create(['name' => 'Department', 'code' => 'NOT-DP', 'branch_id' => $branch->id]);
    $employee = User::create(['name' => 'Employee', 'email' => 'notice@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'notice-token')]);
    $employee->assignRole('employee');
    $other = User::create(['name' => 'Other', 'email' => 'other-notice@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'other-notice-token')]);
    $other->assignRole('employee');
    $report = Report::create(['employee_id' => $employee->id, 'branch_id' => $branch->id, 'department_id' => $department->id, 'type' => 'daily_report', 'period_start' => now(), 'title' => 'Returned report', 'summary' => 'Summary']);
    $notification = CndNotification::create(['user_id' => $employee->id, 'report_id' => $report->id, 'title' => 'Returned', 'message' => 'Please review']);

    $this->withToken('notice-token')->getJson("/api/notifications/{$notification->id}")->assertOk()->assertJsonPath('source_type', 'report')->assertJsonPath('report.id', $report->id);
    $this->assertDatabaseMissing('cnd_notifications', ['id' => $notification->id, 'read_at' => null]);
    $this->withToken('other-notice-token')->getJson("/api/notifications/{$notification->id}")->assertForbidden();
});

it('includes circular source metadata inside notifications', function () {
    $this->seed(RolePermissionSeeder::class);
    $branch = Branch::create(['name' => 'Branch', 'code' => 'CIR-NOT']);
    $department = Department::create(['name' => 'Department', 'code' => 'CIR-NOT-DP', 'branch_id' => $branch->id]);
    $issuer = User::create(['name' => 'Issuer', 'email' => 'issuer@test.local', 'password' => 'password', 'role' => 'department_head', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'issuer-token')]);
    $issuer->assignRole('department_head');
    $employee = User::create(['name' => 'Employee', 'email' => 'receiver@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'receiver-token')]);
    $employee->assignRole('employee');
    $circular = \App\Models\Circular::create(['issuer_id' => $issuer->id, 'title' => 'Circular title', 'content' => 'Circular content', 'audience' => 'department_all']);
    $circular->recipients()->sync([$employee->id]);
    $notification = CndNotification::create(['user_id' => $employee->id, 'circular_id' => $circular->id, 'title' => 'New circular', 'message' => 'Circular content']);

    $this->withToken('receiver-token')->getJson("/api/notifications/{$notification->id}")->assertOk()->assertJsonPath('source_type', 'circular')->assertJsonPath('circular.id', $circular->id)->assertJsonPath('circular.issuer.id', $issuer->id);
});

it('allows employees to update only their own returned report', function () {
    $this->seed(RolePermissionSeeder::class);
    $branch = Branch::create(['name' => 'Branch', 'code' => 'RET-BR']);
    $department = Department::create(['name' => 'Department', 'code' => 'RET-DP', 'branch_id' => $branch->id]);
    $employee = User::create(['name' => 'Employee', 'email' => 'returned@test.local', 'password' => 'password', 'role' => 'employee', 'branch_id' => $branch->id, 'department_id' => $department->id, 'api_token' => hash('sha256', 'returned-token')]);
    $employee->assignRole('employee');
    $report = Report::create(['employee_id' => $employee->id, 'branch_id' => $branch->id, 'department_id' => $department->id, 'type' => 'daily_report', 'period_start' => now(), 'title' => 'Returned report', 'summary' => 'Summary', 'status' => 'returned']);

    $this->withToken('returned-token')->putJson("/api/reports/{$report->id}", [
        'type' => 'daily_report', 'period_start' => now()->toDateString(), 'title' => 'Corrected report', 'summary' => 'Corrected summary',
    ])->assertOk()->assertJsonPath('title', 'Corrected report')->assertJsonPath('status', 'returned');
    $this->assertDatabaseHas('report_actions', ['report_id' => $report->id, 'action' => 'updated_returned']);
});

it('prevents regular employees from corresponding with the general manager but allows office users', function () {
    $this->seed(RolePermissionSeeder::class);
    $branch=Branch::create(['name'=>'Branch','code'=>'COM-BR']);$department=Department::create(['name'=>'Department','code'=>'COM-DP','branch_id'=>$branch->id]);
    $general=User::create(['name'=>'General','email'=>'com-general@test.local','password'=>'password','role'=>'general_manager']);$general->assignRole('general_manager');
    $employee=User::create(['name'=>'Employee','email'=>'com-employee@test.local','password'=>'password','role'=>'employee','branch_id'=>$branch->id,'department_id'=>$department->id,'api_token'=>hash('sha256','com-employee')]);$employee->assignRole('employee');
    $office=Office::create(['name'=>'Registry','code'=>'COM-OFF']);$officeUser=User::create(['name'=>'Registry user','email'=>'office@test.local','password'=>'password','role'=>'employee','office_id'=>$office->id,'api_token'=>hash('sha256','com-office')]);$officeUser->assignRole('employee');
    $payload=['recipient_id'=>$general->id,'subject'=>'Subject','content'=>'Content','purpose'=>'Purpose','allow_reply'=>true];
    $this->withToken('com-employee')->postJson('/api/correspondences',$payload)->assertForbidden();
    $this->withToken('com-office')->postJson('/api/correspondences',$payload)->assertCreated();
});

it('limits department circulars to employees in the issuing department', function () {
    $this->seed(RolePermissionSeeder::class);
    $branch=Branch::create(['name'=>'Branch','code'=>'CIR-BR']);$departmentA=Department::create(['name'=>'A','code'=>'CIR-A','branch_id'=>$branch->id]);$departmentB=Department::create(['name'=>'B','code'=>'CIR-B','branch_id'=>$branch->id]);
    $head=User::create(['name'=>'Head','email'=>'cir-head@test.local','password'=>'password','role'=>'department_head','branch_id'=>$branch->id,'department_id'=>$departmentA->id,'api_token'=>hash('sha256','cir-head')]);$head->assignRole('department_head');
    $employeeA=User::create(['name'=>'A','email'=>'cir-a@test.local','password'=>'password','role'=>'employee','branch_id'=>$branch->id,'department_id'=>$departmentA->id,'api_token'=>hash('sha256','cir-a')]);$employeeA->assignRole('employee');
    $employeeB=User::create(['name'=>'B','email'=>'cir-b@test.local','password'=>'password','role'=>'employee','branch_id'=>$branch->id,'department_id'=>$departmentB->id,'api_token'=>hash('sha256','cir-b')]);$employeeB->assignRole('employee');
    $this->withToken('cir-head')->postJson('/api/circulars',['title'=>'Circular','content'=>'Content','audience'=>'department_all'])->assertCreated();
    $this->withToken('cir-a')->getJson('/api/circulars')->assertOk()->assertJsonCount(1);
    $this->withToken('cir-b')->getJson('/api/circulars')->assertOk()->assertJsonCount(0);
});

it('allows employees to update their own three detail records', function () {
    $this->seed(RolePermissionSeeder::class);
    $employee=User::create(['name'=>'Self editor','email'=>'self-details@test.local','password'=>'password','role'=>'employee','api_token'=>hash('sha256','self-details')]);$employee->assignRole('employee');
    $this->withToken('self-details')->putJson('/api/profile/details',[
        'personal_details'=>['height_cm'=>175,'shoe_size'=>'42','shirt_size'=>'L'],
        'address'=>['city'=>'Damascus','street'=>'Main Street'],
        'family_details'=>['marital_status'=>'married','children_count'=>1],
    ])->assertOk()->assertJsonPath('personal_details.shoe_size','42')->assertJsonPath('address.city','Damascus');
    $this->assertDatabaseHas('user_personal_details',['user_id'=>$employee->id,'shoe_size'=>'42']);
    $this->assertDatabaseHas('user_addresses',['user_id'=>$employee->id,'city'=>'Damascus']);
    $this->assertDatabaseHas('user_family_details',['user_id'=>$employee->id,'children_count'=>1]);
});

it('allows general and database managers to update employee detail records', function () {
    $this->seed(RolePermissionSeeder::class);
    $employee=User::create(['name'=>'Managed employee','email'=>'managed-details@test.local','password'=>'password','role'=>'employee']);$employee->assignRole('employee');
    $manager=User::create(['name'=>'General','email'=>'details-general@test.local','password'=>'password','role'=>'general_manager','api_token'=>hash('sha256','details-general')]);$manager->assignRole('general_manager');
    $this->withToken('details-general')->putJson("/api/employees/{$employee->id}",[
        'personal_details'=>['weight_kg'=>80,'trouser_size'=>'34'],'address'=>['city'=>'Aleppo'],'family_details'=>['marital_status'=>'single'],
    ])->assertOk()->assertJsonPath('personal_details.trouser_size','34');
    $this->assertDatabaseHas('user_personal_details',['user_id'=>$employee->id,'trouser_size'=>'34']);
});

it('scopes task boards by department and management level', function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->seed(RolePermissionSeeder::class);
    $branchA=Branch::create(['name'=>'Branch A','code'=>'TASK-A']);$branchB=Branch::create(['name'=>'Branch B','code'=>'TASK-B']);
    $departmentA=Department::create(['name'=>'Department A','code'=>'TASK-A1','branch_id'=>$branchA->id]);$departmentB=Department::create(['name'=>'Department B','code'=>'TASK-B1','branch_id'=>$branchB->id]);
    $employeeA=User::create(['name'=>'Employee A','email'=>'task-a@test.local','password'=>'password','role'=>'employee','branch_id'=>$branchA->id,'department_id'=>$departmentA->id,'api_token'=>hash('sha256','task-a')]);$employeeA->assignRole('employee');$employeeA->givePermissionTo('tasks.create');
    $employeeB=User::create(['name'=>'Employee B','email'=>'task-b@test.local','password'=>'password','role'=>'employee','branch_id'=>$branchB->id,'department_id'=>$departmentB->id,'api_token'=>hash('sha256','task-b')]);$employeeB->assignRole('employee');
    $branchManager=User::create(['name'=>'Branch manager','email'=>'task-branch@test.local','password'=>'password','role'=>'branch_manager','branch_id'=>$branchA->id,'department_id'=>$departmentA->id,'api_token'=>hash('sha256','task-branch')]);$branchManager->assignRole('branch_manager');
    $general=User::create(['name'=>'General','email'=>'task-general@test.local','password'=>'password','role'=>'general_manager','api_token'=>hash('sha256','task-general')]);$general->assignRole('general_manager');

    $task=$this->withToken('task-a')->postJson('/api/tasks',['department_id'=>$departmentA->id,'assignee_id'=>$employeeA->id,'title'=>'Scoped task','priority'=>'urgent'])->assertCreated()->assertJsonPath('status','archived')->json();
    $this->withToken('task-b')->getJson("/api/tasks?department_id={$departmentA->id}")->assertForbidden();
    $this->withToken('task-branch')->getJson("/api/tasks?department_id={$departmentA->id}")->assertOk()->assertJsonCount(1,'tasks');
    $this->withToken('task-branch')->getJson("/api/tasks?department_id={$departmentB->id}")->assertForbidden();
    $this->withToken('task-general')->getJson('/api/tasks?all=1')->assertOk()->assertJsonCount(1,'tasks')->assertJsonCount(2,'departments');
    $this->withToken('task-a')->patchJson("/api/tasks/{$task['id']}/move",['status'=>'planned'])->assertOk()->assertJsonPath('status','planned');
    $this->assertDatabaseHas('tasks',['id'=>$task['id'],'status'=>'planned']);
    $this->assertDatabaseHas('task_activities',['task_id'=>$task['id'],'actor_id'=>$employeeA->id,'action'=>'task_created']);
    $this->assertDatabaseHas('task_activities',['task_id'=>$task['id'],'actor_id'=>$employeeA->id,'action'=>'task_moved']);
    $this->withToken('task-a')->getJson("/api/task-activities?department_id={$departmentA->id}")
        ->assertOk()->assertJsonCount(2,'activities')->assertJsonPath('activities.0.action','task_moved');
    $this->withToken('task-b')->getJson("/api/task-activities?department_id={$departmentA->id}")->assertForbidden();
    $file=$this->withToken('task-a')->post('/api/drive-files',[
        'scope'=>'task','department_id'=>$departmentA->id,'task_id'=>$task['id'],
        'files'=>[UploadedFile::fake()->create('task-file.pdf',12,'application/pdf')],
    ],['Accept'=>'application/json'])->assertCreated()->json('0');
    expect($file['name'])->toBe('task-file.pdf');
    $this->assertDatabaseHas('task_activities',['task_id'=>$task['id'],'actor_id'=>$employeeA->id,'action'=>'file_attached']);
    // ختم وقت دخول المرحلة هو أساس حساب مدة كل مرحلة في لوحة الإحصائيات؛
    // مسار المراحل كاملاً حتى الاعتماد مغطّى في TaskWorkflowStagesTest.
    expect(Task::find($task['id'])->stage_entered_at)->not->toBeNull();
});

it('stores private drive files and supports scoped and public downloads', function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->seed(RolePermissionSeeder::class);
    $branch=Branch::create(['name'=>'Drive Branch','code'=>'DRV-BR']);$department=Department::create(['name'=>'Drive Department','code'=>'DRV-DP','branch_id'=>$branch->id]);
    $employee=User::create(['name'=>'Drive Employee','email'=>'drive-employee@test.local','password'=>'password','role'=>'employee','branch_id'=>$branch->id,'department_id'=>$department->id,'api_token'=>hash('sha256','drive-employee')]);$employee->assignRole('employee');
    $coworker=User::create(['name'=>'Drive Coworker','email'=>'drive-coworker@test.local','password'=>'password','role'=>'employee','branch_id'=>$branch->id,'department_id'=>$department->id,'api_token'=>hash('sha256','drive-coworker')]);$coworker->assignRole('employee');
    $outsider=User::create(['name'=>'Drive Outsider','email'=>'drive-outsider@test.local','password'=>'password','role'=>'employee','api_token'=>hash('sha256','drive-outsider')]);$outsider->assignRole('employee');

    $file=$this->withToken('drive-employee')->post('/api/drive-files',[
        'scope'=>'personal','files'=>[UploadedFile::fake()->create('guide.pdf',100,'application/pdf')],
    ],['Accept'=>'application/json'])->assertCreated()->json('0');

    $this->withToken('drive-employee')->postJson("/api/drive-files/{$file['id']}/share",['target'=>'users','user_ids'=>[$coworker->id]])->assertOk()->assertJsonPath('shared_users_count',1);
    $this->withToken('drive-coworker')->getJson('/api/drive-files')->assertOk()->assertJsonCount(1,'files');
    $this->withToken('drive-outsider')->getJson('/api/drive-files')->assertOk()->assertJsonCount(0,'files');
    $linked=$this->withToken('drive-employee')->postJson("/api/drive-files/{$file['id']}/public-link")->assertOk()->json();
    $this->get($linked['public_url'])->assertOk();
    expect($linked['direct_url'])->not->toBeNull();
    Storage::disk('local')->assertExists("drive/users/{$employee->id}/guide.pdf");
    Storage::disk('public')->assertExists("drive-public/drive/users/{$employee->id}/guide.pdf");
    $this->withToken('drive-employee')->get("/api/drive-files/{$file['id']}/preview")->assertOk();
});

it('creates drive folders with inherited access and organization content for database managers', function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->seed(RolePermissionSeeder::class);
    $branch=Branch::create(['name'=>'Folder Branch','code'=>'FLD-BR']);$department=Department::create(['name'=>'Folder Department','code'=>'FLD-DP','branch_id'=>$branch->id]);
    $owner=User::create(['name'=>'Folder Owner','email'=>'folder-owner@test.local','password'=>'password','role'=>'employee','branch_id'=>$branch->id,'department_id'=>$department->id,'api_token'=>hash('sha256','folder-owner')]);$owner->assignRole('employee');
    $recipient=User::create(['name'=>'Folder Recipient','email'=>'folder-recipient@test.local','password'=>'password','role'=>'employee','branch_id'=>$branch->id,'department_id'=>$department->id,'api_token'=>hash('sha256','folder-recipient')]);$recipient->assignRole('employee');
    $databaseManager=User::create(['name'=>'Folder DB','email'=>'folder-db@test.local','password'=>'password','role'=>'database_manager','api_token'=>hash('sha256','folder-db')]);$databaseManager->assignRole('database_manager');

    $folder=$this->withToken('folder-owner')->postJson('/api/drive-folders',['name'=>'Private folder','scope'=>'personal'])->assertCreated()->json();
    expect(Storage::disk('local')->directoryExists("drive/users/{$owner->id}/Private folder"))->toBeTrue();
    $this->withToken('folder-owner')->post('/api/drive-files',['scope'=>'personal','folder_id'=>$folder['id'],'files'=>[UploadedFile::fake()->create('inside.pdf',10,'application/pdf')]],['Accept'=>'application/json'])->assertCreated();
    $this->withToken('folder-owner')->postJson("/api/drive-folders/{$folder['id']}/share",['target'=>'users','user_ids'=>[$recipient->id]])->assertOk();
    $this->withToken('folder-recipient')->getJson('/api/drive-files')->assertOk()->assertJsonCount(1,'folders')->assertJsonCount(1,'files');
    $published=$this->withToken('folder-owner')->postJson("/api/drive-folders/{$folder['id']}/public-link")->assertOk()->assertJsonPath('name','Private folder')->json();
    expect($published['direct_url'])->not->toBeNull();
    Storage::disk('public')->assertExists("drive-public/drive/users/{$owner->id}/Private folder/inside.pdf");

    $organizationFolder=$this->withToken('folder-db')->postJson('/api/drive-folders',['name'=>'Organization folder','scope'=>'organization'])->assertCreated()->assertJsonPath('scope','organization')->json();
    $this->withToken('folder-db')->post('/api/drive-files',['scope'=>'organization','folder_id'=>$organizationFolder['id'],'files'=>[UploadedFile::fake()->create('organization.pdf',10,'application/pdf')]],['Accept'=>'application/json'])->assertCreated()->assertJsonPath('0.scope','organization');
    $this->withToken('folder-recipient')->getJson('/api/drive-files')->assertOk()->assertJsonFragment(['name'=>'Organization folder'])->assertJsonFragment(['name'=>'organization.pdf']);
});
