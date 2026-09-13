<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Circular;
use App\Models\CustomForm;
use App\Models\CustomFormPublication;
use App\Models\CustomFormSubmission;
use App\Models\Department;
use App\Models\CndNotification;
use App\Models\Message;
use App\Models\Office;
use App\Models\Report;
use App\Models\ReportAction;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $branchDamascus = Branch::create(['name' => 'فرع دمشق', 'code' => 'DAM']);
        $branchAleppo = Branch::create(['name' => 'فرع حلب', 'code' => 'ALP']);

        $departmentInfra = Department::create(['name' => 'قسم البنية التحتية', 'code' => 'INFRA', 'branch_id' => $branchDamascus->id]);
        $departmentSupport = Department::create(['name' => 'قسم الدعم الفني', 'code' => 'SUPPORT', 'branch_id' => $branchAleppo->id]);

        $registry = Office::create(['name' => 'الديوان', 'code' => 'REGISTRY']);
        $personnel = Office::create(['name' => 'الذاتية', 'code' => 'PERSONNEL']);
        Office::create(['name' => 'القلم', 'code' => 'CLERICAL']);

        $users = [
            'employee_damascus' => User::create($this->userData([
                'name' => 'سامر الخطيب',
                'email' => 'employee@cnd.local',
                'role' => 'employee',
                'job_title' => 'موظف تقني',
                'employee_number' => 'CND-1042',
                'branch_id' => $branchDamascus->id,
                'department_id' => $departmentInfra->id,
                'employment_type' => 'contract',
            ])),
            'technician_damascus' => User::create($this->userData([
                'name' => 'منى سعيد',
                'email' => 'technician@cnd.local',
                'role' => 'technician',
                'job_title' => 'فنية شبكات',
                'employee_number' => 'CND-1043',
                'branch_id' => $branchDamascus->id,
                'department_id' => $departmentInfra->id,
                'employment_type' => 'fixed',
            ])),
            'employee_aleppo_fixed' => User::create($this->userData([
                'name' => 'ليث الأحمد',
                'email' => 'employee2@cnd.local',
                'role' => 'employee',
                'job_title' => 'موظف دعم',
                'employee_number' => 'CND-2042',
                'branch_id' => $branchAleppo->id,
                'department_id' => $departmentSupport->id,
                'employment_type' => 'fixed',
            ])),
            'employee_aleppo_contract' => User::create($this->userData([
                'name' => 'نور الدين علي',
                'email' => 'employee3@cnd.local',
                'role' => 'employee',
                'job_title' => 'موظف عقود',
                'employee_number' => 'CND-2043',
                'branch_id' => $branchAleppo->id,
                'department_id' => $departmentSupport->id,
                'employment_type' => 'contract',
            ])),
            'department_head_damascus' => User::create($this->userData([
                'name' => 'ليان محمود',
                'email' => 'head@cnd.local',
                'role' => 'department_head',
                'job_title' => 'رئيسة القسم',
                'employee_number' => 'CND-0082',
                'branch_id' => $branchDamascus->id,
                'department_id' => $departmentInfra->id,
                'employment_type' => 'fixed',
            ])),
            'branch_manager_damascus' => User::create($this->userData([
                'name' => 'وسيم ناصر',
                'email' => 'branch@cnd.local',
                'role' => 'branch_manager',
                'job_title' => 'مدير الفرع',
                'employee_number' => 'CND-0017',
                'branch_id' => $branchDamascus->id,
                'department_id' => $departmentInfra->id,
                'employment_type' => 'fixed',
            ])),
            'general_manager' => User::create($this->userData([
                'name' => 'فراس أمين',
                'email' => 'general@cnd.local',
                'role' => 'general_manager',
                'job_title' => 'المدير العام',
                'employee_number' => 'CND-0001',
                'branch_id' => $branchDamascus->id,
                'department_id' => $departmentInfra->id,
                'employment_type' => 'fixed',
            ])),
            'database_manager' => User::create($this->userData([
                'name' => 'محمد ناصر الدين',
                'email' => 'naser@cnd.local',
                'role' => 'database_manager',
                'job_title' => 'مدير قواعد البيانات',
                'employee_number' => 'CND-0000',
                'branch_id' => null,
                'department_id' => null,
                'employment_type' => 'fixed',
            ])),
            'branch_manager_aleppo' => User::create($this->userData([
                'name' => 'رامي منصور',
                'email' => 'branch2@cnd.local',
                'role' => 'branch_manager',
                'job_title' => 'مدير فرع حلب',
                'employee_number' => 'CND-0018',
                'branch_id' => $branchAleppo->id,
                'department_id' => $departmentSupport->id,
                'employment_type' => 'fixed',
            ])),
            'department_head_aleppo' => User::create($this->userData([
                'name' => 'هبة العلي',
                'email' => 'head2@cnd.local',
                'role' => 'department_head',
                'job_title' => 'رئيسة قسم الدعم الفني',
                'employee_number' => 'CND-0083',
                'branch_id' => $branchAleppo->id,
                'department_id' => $departmentSupport->id,
                'employment_type' => 'fixed',
            ])),
            'registry_employee' => User::create($this->userData([
                'name' => 'أمين الديوان',
                'email' => 'registry@cnd.local',
                'role' => 'employee',
                'job_title' => 'أمين الديوان',
                'employee_number' => 'CND-0200',
                'branch_id' => null,
                'department_id' => null,
                'office_id' => $registry->id,
                'employment_type' => 'fixed',
            ])),
            'personnel_employee' => User::create($this->userData([
                'name' => 'سلمى يوسف',
                'email' => 'personnel@cnd.local',
                'role' => 'employee',
                'job_title' => 'موظفة موارد بشرية',
                'employee_number' => 'CND-0201',
                'branch_id' => null,
                'department_id' => null,
                'office_id' => $personnel->id,
                'employment_type' => 'contract',
            ])),
        ];

        foreach ($users as $user) {
            $user->assignRole($user->role);
        }

        $this->seedEmployeeProfiles($users);
        $this->seedReports($users, $branchDamascus, $branchAleppo, $departmentInfra, $departmentSupport);
        $this->seedCorrespondence($users);
        $this->seedCirculars($users, $branchDamascus, $branchAleppo, $departmentInfra, $departmentSupport);
        $this->seedForms($users, $branchDamascus, $branchAleppo, $departmentInfra, $departmentSupport);
        $this->call(FormalCorrespondenceSeeder::class);
        $this->call(HrSeeder::class);
        $this->call(TaskSeeder::class);
        $this->call(DashboardTimelineSeeder::class);
        $this->call(TaskActivitySeeder::class);
    }

    private function userData(array $data): array
    {
        return $data + [
            'password' => 'password',
            'branch_id' => $data['branch_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'office_id' => $data['office_id'] ?? null,
            'employment_type' => $data['employment_type'] ?? 'contract',
        ];
    }

    private function seedEmployeeProfiles(array $users): void
    {
        $users['branch_manager_aleppo']->address()->create(['country' => 'سوريا', 'city' => 'حلب', 'district' => 'الجميلية', 'street' => 'شارع النصر', 'building' => '12']);
        $users['branch_manager_aleppo']->familyDetails()->create(['marital_status' => 'married', 'children_count' => 2, 'emergency_contact_name' => 'سارة منصور', 'emergency_contact_phone' => '0990000000', 'emergency_contact_relation' => 'spouse']);
        $users['branch_manager_aleppo']->personalDetails()->create(['birth_date' => '1987-04-18', 'gender' => 'male', 'height_cm' => 178, 'weight_kg' => 79, 'blood_type' => 'O+', 'shoe_size' => '43', 'trouser_size' => '34', 'shirt_size' => 'L', 'jacket_size' => 'L']);

        $users['department_head_aleppo']->address()->create(['country' => 'سوريا', 'city' => 'حلب', 'district' => 'العزيزية']);
        $users['department_head_aleppo']->personalDetails()->create(['birth_date' => '1991-09-03', 'gender' => 'female', 'height_cm' => 165, 'weight_kg' => 60, 'blood_type' => 'A+', 'shoe_size' => '38', 'trouser_size' => '30', 'shirt_size' => 'M', 'jacket_size' => 'M']);

        $users['employee_damascus']->address()->create(['country' => 'سوريا', 'city' => 'دمشق', 'district' => 'المزة', 'street' => 'شارع الفحيحيل']);
        $users['employee_damascus']->personalDetails()->create(['birth_date' => '1995-01-11', 'gender' => 'male', 'height_cm' => 174, 'weight_kg' => 72, 'blood_type' => 'B+', 'shoe_size' => '42', 'trouser_size' => '33', 'shirt_size' => 'L', 'jacket_size' => 'L']);

        $users['technician_damascus']->address()->create(['country' => 'سوريا', 'city' => 'دمشق', 'district' => 'ركن الدين']);
        $users['technician_damascus']->personalDetails()->create(['birth_date' => '1993-05-22', 'gender' => 'female', 'height_cm' => 168, 'weight_kg' => 61, 'blood_type' => 'AB+', 'shoe_size' => '39', 'trouser_size' => '31', 'shirt_size' => 'M', 'jacket_size' => 'M']);
    }

    private function seedReports(array $users, Branch $branchDamascus, Branch $branchAleppo, Department $departmentInfra, Department $departmentSupport): void
    {
        $reportOne = Report::create([
            'employee_id' => $users['employee_damascus']->id,
            'branch_id' => $branchDamascus->id,
            'department_id' => $departmentInfra->id,
            'type' => 'daily_report',
            'period_start' => now()->subDays(2)->toDateString(),
            'period_end' => now()->subDay()->toDateString(),
            'title' => 'متابعة جاهزية مخدمات الفرع',
            'summary' => 'تم فحص الخدمات الأساسية ومراجعة سجلات الأداء اليومية.',
            'achievements' => 'إغلاق ثلاث ملاحظات وتحسين زمن الاستجابة.',
            'challenges' => 'تأخر توريد وحدة التخزين.',
            'next_steps' => 'متابعة النسخ الاحتياطي واختبار الاستعادة.',
            'status' => 'department_review',
            'current_reviewer_role' => 'department_head',
            'submitted_at' => now()->subDay(),
        ]);
        $this->reportAction($reportOne, $users['employee_damascus'], 'submit', null, 'department_review', 'إرسال التقرير للمراجعة.');

        $reportTwo = Report::create([
            'employee_id' => $users['technician_damascus']->id,
            'branch_id' => $branchDamascus->id,
            'department_id' => $departmentInfra->id,
            'type' => 'weekly_plan',
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->subDays(1)->toDateString(),
            'title' => 'خطة أسبوعية لصيانة الشبكة',
            'summary' => 'إجراءات الصيانة المجدولة تمت وفق الخطة.',
            'achievements' => 'ترقية السويتشات الرئيسية وتحديث النسخ الاحتياطي.',
            'challenges' => 'تأخير طفيف في توفير قطع الغيار.',
            'next_steps' => 'إغلاق الصيانة النهائية ومراجعة التنبيهات.',
            'status' => 'returned',
            'current_reviewer_role' => 'employee',
            'submitted_at' => now()->subDays(2),
        ]);
        $this->reportAction($reportTwo, $users['technician_damascus'], 'submit', null, 'department_review', 'رفع الخطة الأسبوعية.');
        $this->reportAction($reportTwo, $users['department_head_damascus'], 'return', 'department_review', 'returned', 'تمت إعادة الخطة لإضافة تفاصيل أوضح.');

        $reportThree = Report::create([
            'employee_id' => $users['employee_aleppo_fixed']->id,
            'branch_id' => $branchAleppo->id,
            'department_id' => $departmentSupport->id,
            'type' => 'monthly_report',
            'period_start' => now()->subWeeks(4)->toDateString(),
            'period_end' => now()->subWeeks(2)->toDateString(),
            'title' => 'ملخص دعم المستخدمين',
            'summary' => 'تمت معالجة طلبات الدعم الأساسية خلال الفترة.',
            'achievements' => 'إنهاء 18 طلب دعم.',
            'challenges' => 'نقص مؤقت في بعض صلاحيات الأنظمة.',
            'next_steps' => 'أتمتة فتح التذاكر وتحديث قاعدة المعرفة.',
            'status' => 'branch_review',
            'current_reviewer_role' => 'branch_manager',
            'submitted_at' => now()->subDays(6),
        ]);
        $this->reportAction($reportThree, $users['employee_aleppo_fixed'], 'submit', null, 'department_review', 'إرسال التقرير الشهري.');

        CndNotification::create([
            'user_id' => $users['department_head_damascus']->id,
            'title' => 'تقرير يومي جديد',
            'message' => 'وصل تقرير جديد من سامر الخطيب للمراجعة.',
            'report_id' => $reportOne->id,
        ]);
        CndNotification::create([
            'user_id' => $users['employee_damascus']->id,
            'title' => 'تقرير معاد للتدقيق',
            'message' => 'أُعيد تقريرك لإضافة تفاصيل أوضح.',
            'report_id' => $reportTwo->id,
        ]);
        CndNotification::create([
            'user_id' => $users['branch_manager_aleppo']->id,
            'title' => 'تقرير فرعي جديد',
            'message' => 'تم رفع تقرير شهري من قسم الدعم الفني.',
            'report_id' => $reportThree->id,
        ]);
    }

    private function seedCorrespondence(array $users): void
    {
        $messageOne = Message::create([
            'sender_id' => $users['employee_damascus']->id,
            'recipient_id' => $users['department_head_damascus']->id,
            'subject' => 'طلب توفير قطع غيار',
            'content' => 'يرجى الموافقة على طلب قطع الغيار اللازمة لاستكمال الصيانة.',
            'purpose' => 'تسهيل أعمال الصيانة داخل القسم.',
            'allow_reply' => true,
        ]);
        $messageOne->replies()->create([
            'sender_id' => $users['department_head_damascus']->id,
            'content' => 'تمت الموافقة، وسيتم تنسيق التوريد خلال هذا الأسبوع.',
        ]);

        Message::create([
            'sender_id' => $users['branch_manager_damascus']->id,
            'recipient_id' => $users['general_manager']->id,
            'subject' => 'موازنة صيانة الشبكة',
            'content' => 'نرفق خلاصة احتياج الصيانة للفصل القادم.',
            'purpose' => 'اعتماد مخصصات الصيانة.',
            'allow_reply' => false,
        ]);
    }

    private function seedCirculars(array $users, Branch $branchDamascus, Branch $branchAleppo, Department $departmentInfra, Department $departmentSupport): void
    {
        $generalCircular = Circular::create([
            'issuer_id' => $users['general_manager']->id,
            'title' => 'ضبط ساعات العمل خلال الصيانة الدورية',
            'content' => 'يرجى الالتزام بجدول الصيانة والتنسيق المسبق مع الإدارات المعنية.',
            'audience' => 'general_all',
        ]);
        $generalRecipients = User::query()->where('is_active', true)->whereKeyNot($users['general_manager']->id)->pluck('id');
        $generalCircular->recipients()->attach($generalRecipients->mapWithKeys(fn (int $id) => [$id => ['read_at' => null]])->all());

        $branchCircular = Circular::create([
            'issuer_id' => $users['branch_manager_damascus']->id,
            'title' => 'تحديث جداول مناوبات الفرع',
            'content' => 'تم اعتماد مناوبات جديدة لقسم البنية التحتية ابتداءً من الأسبوع القادم.',
            'audience' => 'branch_all',
        ]);
        $branchCircular->recipients()->attach([
            $users['department_head_damascus']->id => ['read_at' => now()->subHours(2)],
            $users['employee_damascus']->id => ['read_at' => null],
            $users['technician_damascus']->id => ['read_at' => null],
        ]);

        $departmentCircular = Circular::create([
            'issuer_id' => $users['department_head_aleppo']->id,
            'title' => 'تأكيد حضور التدريب',
            'content' => 'يرجى تأكيد الحضور للدورة الفنية في الموعد المحدد.',
            'audience' => 'department_all',
        ]);
        $departmentCircular->recipients()->attach([
            $users['employee_aleppo_fixed']->id => ['read_at' => null],
            $users['employee_aleppo_contract']->id => ['read_at' => null],
        ]);

        CndNotification::create([
            'user_id' => $users['employee_damascus']->id,
            'title' => 'تعميم جديد',
            'message' => 'وصل تعميم من إدارة الفرع يخص جدول المناوبات.',
            'circular_id' => $branchCircular->id,
        ]);
        CndNotification::create([
            'user_id' => $users['employee_aleppo_fixed']->id,
            'title' => 'تعميم تدريبي',
            'message' => 'وصل تعميم خاص بحضور التدريب الفني.',
            'circular_id' => $departmentCircular->id,
        ]);
    }

    private function seedForms(array $users, Branch $branchDamascus, Branch $branchAleppo, Department $departmentInfra, Department $departmentSupport): void
    {
        $surveyForm = $this->createForm(
            creator: $users['database_manager'],
            title: 'استبيان الرضا الوظيفي',
            description: 'استبيان عام لتقييم بيئة العمل والاحتياجات التشغيلية.',
            targetGroup: 'all_staff',
            defaultScope: 'organization',
            defaultDurationDays: 10,
            fields: [
                ['field_key' => 'satisfaction_level', 'label' => 'مستوى الرضا', 'input_type' => 'select', 'options' => ['1', '2', '3', '4', '5'], 'placeholder' => 'اختر التقييم', 'help_text' => 'من 1 إلى 5', 'is_required' => true],
                ['field_key' => 'preferred_channels', 'label' => 'قنوات المتابعة المناسبة', 'input_type' => 'checkbox_group', 'options' => ['البريد الإلكتروني', 'الرسائل الداخلية', 'اجتماع مباشر'], 'help_text' => 'يمكن اختيار أكثر من خيار', 'is_required' => false],
                ['field_key' => 'priority_level', 'label' => 'أولوية المعالجة', 'input_type' => 'radio', 'options' => ['عادية', 'متوسطة', 'عاجلة'], 'help_text' => 'اختر أولوية واحدة', 'is_required' => true],
                ['field_key' => 'main_challenge', 'label' => 'أهم تحدٍ', 'input_type' => 'textarea', 'placeholder' => 'اذكر التحدي الرئيسي', 'help_text' => 'يمكنك الشرح بالتفصيل', 'is_required' => true],
                ['field_key' => 'suggestion', 'label' => 'اقتراح تحسين', 'input_type' => 'textarea', 'placeholder' => 'ما الذي تقترحه؟', 'is_required' => false],
                ['field_key' => 'department_need', 'label' => 'احتياج القسم', 'input_type' => 'text', 'placeholder' => 'مثال: أجهزة إضافية', 'is_required' => false],
            ]
        );

        $surveyPublication = $this->publishForm($surveyForm, $users['general_manager'], [
            'scope' => 'organization',
            'target_group' => 'all_staff',
            'visible_from' => now()->subDay(),
            'duration_days' => 14,
            'message' => 'يرجى تعبئة الاستبيان قبل نهاية الفترة المحددة.',
        ]);

        $surveyRepublish = $this->publishForm($surveyForm, $users['database_manager'], [
            'scope' => 'organization',
            'target_group' => 'all_staff',
            'visible_from' => now()->subHours(6),
            'duration_days' => 7,
            'message' => 'إعادة تعميم الاستبيان بعد تحديث بعض الحقول.',
        ]);

        $branchForm = $this->createForm(
            creator: $users['database_manager'],
            title: 'نموذج حصر العهدة التقنية',
            description: 'نموذج مخصص لتحديث بيانات العهد والموجودات التقنية داخل الفرع.',
            targetGroup: 'branch_managers_heads_employees',
            defaultScope: 'branch',
            defaultDurationDays: 7,
            fields: [
                ['field_key' => 'asset_number', 'label' => 'رقم العهدة', 'input_type' => 'text', 'placeholder' => 'مثال: IT-204', 'is_required' => true],
                ['field_key' => 'device_name', 'label' => 'اسم الجهاز', 'input_type' => 'text', 'placeholder' => 'اسم المعدة أو الجهاز', 'is_required' => true],
                ['field_key' => 'condition', 'label' => 'الحالة الحالية', 'input_type' => 'select', 'options' => ['جيدة', 'متوسطة', 'تحتاج صيانة'], 'is_required' => true],
                ['field_key' => 'notes', 'label' => 'ملاحظات', 'input_type' => 'textarea', 'placeholder' => 'أي معلومات إضافية', 'is_required' => false],
            ]
        );

        $branchPublication = $this->publishForm($branchForm, $users['branch_manager_damascus'], [
            'scope' => 'branch',
            'branch_id' => $branchDamascus->id,
            'target_group' => 'branch_managers_heads_employees',
            'visible_from' => now()->subHours(12),
            'duration_days' => 9,
            'message' => 'يرجى تعبئة النموذج لجميع موظفي فرع دمشق.',
        ]);

        $draftForm = $this->createForm(
            creator: $users['database_manager'],
            title: 'نموذج بيانات الموظف الإضافية',
            description: 'نسخة تجريبية غير معممة لاختبار المصمم والحقول.',
            targetGroup: 'fixed_employees',
            defaultScope: 'department',
            defaultDurationDays: 5,
            isActive: true,
            fields: [
                ['field_key' => 'favorite_tool', 'label' => 'الأداة المفضلة', 'input_type' => 'text', 'placeholder' => 'مثال: Outlook', 'is_required' => false],
                ['field_key' => 'shift_ready', 'label' => 'جاهزية المناوبة', 'input_type' => 'checkbox', 'is_required' => false],
                ['field_key' => 'training_need', 'label' => 'احتياج تدريبي', 'input_type' => 'textarea', 'placeholder' => 'اقترح دورة أو تدريب', 'is_required' => false],
            ]
        );

        $this->submitForm($surveyForm, $surveyPublication, $users['employee_damascus'], [
            'satisfaction_level' => '5',
            'preferred_channels' => ['البريد الإلكتروني', 'الرسائل الداخلية'],
            'priority_level' => 'متوسطة',
            'main_challenge' => 'تأخر توريد بعض القطع.',
            'suggestion' => 'تخصيص قناة طلبات أسرع.',
            'department_need' => 'جهاز تخزين إضافي',
        ], 1, now()->subHours(20));

        $this->submitForm($surveyForm, $surveyPublication, $users['technician_damascus'], [
            'satisfaction_level' => '4',
            'preferred_channels' => ['الرسائل الداخلية', 'اجتماع مباشر'],
            'priority_level' => 'عاجلة',
            'main_challenge' => 'تعدد الطلبات في نفس الفترة.',
            'suggestion' => 'توزيع البلاغات حسب الأولوية.',
            'department_need' => 'سويتش احتياطي',
        ], 1, now()->subHours(18));

        $this->submitForm($surveyForm, $surveyRepublish, $users['employee_aleppo_fixed'], [
            'satisfaction_level' => '3',
            'preferred_channels' => ['البريد الإلكتروني'],
            'priority_level' => 'عادية',
            'main_challenge' => 'الحاجة إلى أدوات تنظيم إضافية.',
            'suggestion' => 'تفعيل لوحة متابعة أسبوعية.',
            'department_need' => 'جهاز لابتوب إضافي',
        ], 2, now()->subHours(4));

        $this->submitForm($surveyForm, $surveyRepublish, $users['employee_aleppo_contract'], [
            'satisfaction_level' => '4',
            'preferred_channels' => ['الرسائل الداخلية'],
            'priority_level' => 'متوسطة',
            'main_challenge' => 'أحيانًا يتأخر الرد على بعض الطلبات.',
            'suggestion' => 'تحديد وقت استجابة واضح.',
            'department_need' => 'رخصة برنامج متابعة',
        ], 2, now()->subHours(3));

        $this->submitForm($branchForm, $branchPublication, $users['department_head_damascus'], [
            'asset_number' => 'IT-204',
            'device_name' => 'سويتش رئيسي',
            'condition' => 'جيدة',
            'notes' => 'تمت المراجعة الأسبوعية بنجاح.',
        ], 1, now()->subHours(11));

        $this->submitForm($branchForm, $branchPublication, $users['employee_damascus'], [
            'asset_number' => 'IT-311',
            'device_name' => 'حاسوب مكتبي',
            'condition' => 'تحتاج صيانة',
            'notes' => 'يحتاج تبديل مزود الطاقة.',
        ], 1, now()->subHours(10));

        $this->submitForm($branchForm, $branchPublication, $users['technician_damascus'], [
            'asset_number' => 'IT-120',
            'device_name' => 'طابعة شبكية',
            'condition' => 'متوسطة',
            'notes' => 'تم تغيير الحبر وتمت التجربة.',
        ], 1, now()->subHours(9));

        CndNotification::create([
            'user_id' => $users['employee_damascus']->id,
            'title' => 'نموذج مطلوب',
            'message' => 'تم تعميم استبيان الرضا الوظيفي وتعبئته مطلوب.',
            'custom_form_id' => $surveyForm->id,
            'custom_form_publication_id' => $surveyRepublish->id,
        ]);
        CndNotification::create([
            'user_id' => $users['department_head_damascus']->id,
            'title' => 'نموذج تعبئة جديد',
            'message' => 'تمت تعبئة نموذج حصر العهدة التقنية من أحد الموظفين.',
            'custom_form_id' => $branchForm->id,
            'custom_form_publication_id' => $branchPublication->id,
        ]);
        CndNotification::create([
            'user_id' => $users['database_manager']->id,
            'title' => 'نموذج غير معمم',
            'message' => 'يوجد نموذج تجريبي غير معمم قيد الإعداد.',
            'custom_form_id' => $draftForm->id,
        ]);
    }

    private function createForm(
        User $creator,
        string $title,
        string $description,
        string $targetGroup,
        string $defaultScope,
        int $defaultDurationDays,
        array $fields,
        bool $isActive = true,
    ): CustomForm {
        $form = CustomForm::create([
            'creator_id' => $creator->id,
            'title' => $title,
            'description' => $description,
            'target_group' => $targetGroup,
            'default_scope' => $defaultScope,
            'default_duration_days' => $defaultDurationDays,
            'is_active' => $isActive,
        ]);

        foreach ($fields as $index => $field) {
            $form->fields()->create([
                'field_key' => $field['field_key'],
                'label' => $field['label'],
                'input_type' => $field['input_type'],
                'options' => $field['options'] ?? null,
                'placeholder' => $field['placeholder'] ?? null,
                'help_text' => $field['help_text'] ?? null,
                'is_required' => (bool) ($field['is_required'] ?? false),
                'sort_order' => $index,
            ]);
        }

        return $form->load(['creator:id,name,job_title', 'fields']);
    }

    private function publishForm(CustomForm $form, User $issuer, array $data): CustomFormPublication
    {
        $publication = CustomFormPublication::create([
            'custom_form_id' => $form->id,
            'issuer_id' => $issuer->id,
            'scope' => $data['scope'] ?? $form->default_scope,
            'target_group' => $data['target_group'] ?? $form->target_group,
            'branch_id' => $data['branch_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'visible_from' => $data['visible_from'] ?? now(),
            'visible_until' => $data['visible_until'] ?? (
                isset($data['duration_days'])
                    ? now()->addDays((int) $data['duration_days'])
                    : now()->addDays($form->default_duration_days ?? 7)
            ),
            'status' => $data['status'] ?? 'active',
            'message' => $data['message'] ?? null,
        ]);

        $recipients = User::query()
            ->where('is_active', true)
            ->whereKeyNot($issuer->id)
            ->when($publication->scope === 'branch', fn ($query) => $query->where('branch_id', $publication->branch_id))
            ->when($publication->scope === 'department', fn ($query) => $query->where('department_id', $publication->department_id))
            ->get();

        foreach ($recipients as $recipient) {
            CndNotification::create([
                'user_id' => $recipient->id,
                'title' => $form->title,
                'message' => $publication->message ?: 'تم تعميم نموذج جديد ضمن نطاقك.',
                'custom_form_id' => $form->id,
                'custom_form_publication_id' => $publication->id,
            ]);
        }

        return $publication;
    }

    private function submitForm(CustomForm $form, CustomFormPublication $publication, User $user, array $payload, int $version, $submittedAt): void
    {
        CustomFormSubmission::create([
            'custom_form_id' => $form->id,
            'custom_form_publication_id' => $publication->id,
            'user_id' => $user->id,
            'version' => $version,
            'payload' => $payload,
            'submitted_at' => $submittedAt,
        ]);
    }

    private function reportAction(Report $report, User $actor, string $action, ?string $fromStatus, string $toStatus, string $note): void
    {
        ReportAction::create([
            'report_id' => $report->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
        ]);
    }
}
