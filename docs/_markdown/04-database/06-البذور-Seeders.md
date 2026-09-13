# البذور — Seeders

`BackEnd/database/seeders/` — ثمانية ملفات.

---

## ترتيب التنفيذ

`DatabaseSeeder.php` هو المنسّق:

```php
public function run(): void
{
    $this->call(RolePermissionSeeder::class);      // 1. الأدوار والصلاحيات أولاً

    // 2. الهيكل التنظيمي والمستخدمون والتقارير والرسائل والتعاميم والفورمات
    //    (مباشرةً داخل هذا الملف)

    $this->call(FormalCorrespondenceSeeder::class); // 3
    $this->call(HrSeeder::class);                   // 4
    $this->call(TaskSeeder::class);                 // 5
    $this->call(DashboardTimelineSeeder::class);    // 6
    $this->call(TaskActivitySeeder::class);         // 7
}
```

> `RolePermissionSeeder` **أولاً دائماً** — لا يمكن استدعاء `assignRole()` قبل
> وجود الأدوار.

الصنف يستخدم `use WithoutModelEvents;` لمنع إطلاق أحداث الموديلات أثناء البذر
(وإلا انطلقت إشعارات وأحداث بث لبيانات تجريبية).

---

## `RolePermissionSeeder.php`

**الأهم على الإطلاق** — مصدر الحقيقة للأدوار والصلاحيات.

```php
app(PermissionRegistrar::class)->forgetCachedPermissions();

$permissions = ['reports.view', 'reports.create', /* ~40 صلاحية */];
foreach ($permissions as $permission) {
    Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
}

$roles = [
    'employee' => ['reports.view', 'reports.create', /* ... */],
    'technician' => [/* نفس صلاحيات الموظف */],
    'department_head' => [/* ... */],
    'branch_manager' => [/* ... */],
    'general_manager' => [/* ... */],
    'database_manager' => [/* ... */],
];

foreach ($roles as $name => $rolePermissions) {
    Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->syncPermissions($rolePermissions);
}

Permission::whereIn('name', ['project_master.view', 'project_master.manage'])->delete();
```

| النقطة | التفصيل |
| --- | --- |
| `firstOrCreate` | آمن للتكرار — يمكن إعادة تشغيله |
| `syncPermissions` | **يستبدل** لا يضيف — الملف هو مصدر الحقيقة |
| `forgetCachedPermissions()` | أول سطر — يمسح الكاش قبل البدء |
| حذف `project_master.*` | تنظيف صلاحيات ميزة ملغاة |
| `office_manager` غير معرّف هنا | يُنشأ في ترحيلات المنح |

### إعادة التشغيل وحده

```powershell
php artisan db:seed --class=RolePermissionSeeder
```

آمن على قاعدة فيها بيانات — لا يلمس المستخدمين ولا السجلات.

> **تنبيه:** `syncPermissions` يمسح أي صلاحية مُنحت لدور خارج هذا الملف.
> إن منحت صلاحية عبر ترحيل ثم أعدت تشغيل البذرة، ستُفقد. **أضفها إلى `$roles` هنا أيضاً.**

---

## `DatabaseSeeder.php` — البيانات الأساسية

### الهيكل التنظيمي

| الفروع | الأقسام | المكاتب |
| --- | --- | --- |
| `فرع دمشق` (DAM) | `قسم البنية التحتية` (INFRA) | `الديوان` (REGISTRY) |
| `فرع حلب` (ALP) | `قسم الدعم الفني` (SUPPORT) | `الذاتية` (PERSONNEL) |
| | | `القلم` (CLERICAL) |

### الحسابات

كلمة المرور للجميع: `password`

| الاسم | البريد | الدور | الموقع |
| --- | --- | --- | --- |
| سامر الخطيب | `employee@cnd.local` | `employee` | دمشق / البنية التحتية |
| منى سعيد | `technician@cnd.local` | `technician` | دمشق / البنية التحتية |
| ليث الأحمد | `employee2@cnd.local` | `employee` | حلب / الدعم الفني |
| — | `head@cnd.local` | `department_head` | دمشق |
| — | `head2@cnd.local` | `department_head` | حلب / الدعم الفني |
| — | `branch@cnd.local` | `branch_manager` | دمشق |
| — | `branch2@cnd.local` | `branch_manager` | حلب |
| — | `general@cnd.local` | `general_manager` | — |
| ناصر | `naser@cnd.local` | `database_manager` | — |

كل حساب يحصل على `employee_number` (`CND-1042`, `CND-1043`, …) و`employment_type`
(`fixed` أو `contract`) — لاختبار الجمهور المستهدف في الفورمات.

### البيانات التجريبية الأخرى

تقارير بحالات مختلفة، `report_actions`، إشعارات، رسائل، تعاميم، فورمات ونشرات وإجابات —
كلها منشأة داخل `DatabaseSeeder` مباشرةً لتغطية كل شاشات النظام ببيانات واقعية.

---

## البذور المتخصصة

| البذرة | تنشئ |
| --- | --- |
| `FormalCorrespondenceSeeder` | جهات خارجية + مراسلات بمحطات ووثائق — لتجريب شاشة المراسلات |
| `HrSeeder` | طلبات موارد بشرية بحالات مختلفة + أرصدة إجازات |
| `TaskSeeder` | مهام موزّعة على الحالات والأولويات لملء لوحة المهام |
| `TaskActivitySeeder` | سجل حركات للمهام |
| `DashboardTimelineSeeder` | **بيانات موزّعة زمنياً** لمخططات لوحة المعلومات |
| `OfficeSeeder` | مكاتب إضافية (غير مستدعاة من `DatabaseSeeder`) |

### `DashboardTimelineSeeder` — لماذا منفصلة

مخططات اللوحة تعرض ستة أشهر للتقارير وثمانية أسابيع للمهام. البيانات العادية
تتركّز في تاريخ البذر، فتظهر المخططات فارغة إلا في نقطة واحدة. هذه البذرة توزّع
السجلات على الفترة كاملة.

الاختبار في `tests/Feature/OrganizationWorkflowTest.php` يتحقق من ذلك:

```php
->assertJsonCount(6, 'analytics.report_trend')
->assertJsonCount(8, 'analytics.task_trend')
```

---

## التشغيل

```powershell
php artisan db:seed                                    # كل البذور
php artisan db:seed --class=RolePermissionSeeder       # واحدة فقط
php artisan migrate --seed                             # ترحيل + بذر
php artisan migrate:fresh --seed                       # ⚠ حذف كل شيء وإعادة البناء
```

> **`migrate:fresh --seed` يحذف كل البيانات.** لا تستخدمه إلا في التطوير.
> على الإنتاج استخدم `php artisan migrate --force` فقط.

---

## البذور في الاختبارات

```php
$this->seed(RolePermissionSeeder::class);
```

الاختبارات **لا تستخدم `DatabaseSeeder` كاملاً** — تبذر الأدوار فقط ثم تنشئ ما تحتاجه
بنفسها. هذا أسرع ويجعل كل اختبار مستقلاً وواضح المدخلات.

---

## كتابة بذرة جديدة

```powershell
php artisan make:seeder AssetSeeder
```

```php
class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::where('code', 'INFRA')->first();
        if (! $department) return;                      // لا تفشل إن غاب المتطلب

        $holder = User::where('email', 'employee@cnd.local')->first();

        foreach ([
            ['code' => 'AST-001', 'name' => 'حاسوب محمول',  'status' => 'in_service'],
            ['code' => 'AST-002', 'name' => 'مقسّم شبكة',    'status' => 'maintenance'],
        ] as $row) {
            Asset::firstOrCreate(
                ['code' => $row['code']],
                $row + ['department_id' => $department->id, 'holder_id' => $holder?->id],
            );
        }
    }
}
```

ثم استدعِها من `DatabaseSeeder::run()`.

### قواعد

| القاعدة | السبب |
| --- | --- |
| `firstOrCreate` لا `create` | تُشغَّل البذرة أكثر من مرة |
| تحقّق من وجود المتطلبات | البذرة قد تُشغَّل وحدها |
| بيانات عربية واقعية | البيانات التجريبية تُستخدم للعرض |
| غطِّ كل الحالات والأدوار | لتجريب كل مسارات الواجهة |
| لا تعتمد على معرّفات ثابتة | ابحث بالرمز أو البريد لا بـ`id = 1` |
| استدعِها من `DatabaseSeeder` | وإلا لن تعمل مع `--seed` |
