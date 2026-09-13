# الترحيلات — Migrations

54 ترحيلاً في `BackEnd/database/migrations/`. التنفيذ بالترتيب الزمني للاسم.

---

## الخط الزمني

### الأساس — Laravel

| الترحيل | المحتوى |
| --- | --- |
| `0001_01_01_000000_create_users_table` | `users`, `password_reset_tokens`, `sessions` |
| `0001_01_01_000001_create_cache_table` | `cache`, `cache_locks` |
| `0001_01_01_000002_create_jobs_table` | `jobs`, `job_batches`, `failed_jobs` |

### 12 يونيو 2026 — الهيكل التنظيمي والتقارير

| الترحيل | المحتوى |
| --- | --- |
| `000001_create_branches_table` | `branches` |
| `000002_create_departments_table` | `departments` |
| `000003_extend_users_for_organization` | يضيف `role`, `branch_id`, `api_token`, `is_active` … |
| `000004_create_reports_table` | `reports` |
| `000005_create_report_actions_table` | `report_actions` |
| `000006_create_ncm_notifications_table` | جدول الإشعارات (يُعاد تسميته لاحقاً) |
| `000007–000010` | `user_addresses`, `user_family_details`, `user_personal_details`, `user_clothing_sizes` |
| `113206_create_permission_tables` | جداول spatie الخمسة |
| `120001_create_offices_table` | `offices` |
| `120002–120003` | `correspondences`, `correspondence_replies` |
| `120004_create_circulars_table` | `circulars`, `circular_recipients` |
| `120005_add_circular_id_to_ncm_notifications_table` | ربط الإشعار بالتعميم |
| `120005_merge_clothing_sizes_into_personal_details` | **يحذف** `user_clothing_sizes` ويدمجها |

### 13–14 يونيو — الفورمات والمهام والملفات

| الترحيل | المحتوى |
| --- | --- |
| `13_000001_create_custom_forms_tables` | أربعة جداول للفورمات |
| `13_000002` | ربط الإشعار بالفورم |
| `13_000003_add_employment_type_to_users_table` | `employment_type` |
| `13_000004` | مرفقات المراسلات والتعاميم |
| `13_000005_create_tasks_table` | `tasks` |
| `13_000006_create_drive_files_tables` | `drive_files`, `drive_file_shares` |
| `13_000007_create_drive_folders_table` | `drive_folders`, `drive_folder_shares`, ويضيف `folder_id` |
| `13_000008_add_physical_paths_to_drive_items` | `path` و`public_path` |
| `14_000001_create_task_activities_table` | `task_activities` |
| `14_000002_rename_ncm_brand_to_cnd` | **إعادة التسمية الشاملة** |

### 16–19 يونيو — المراسلات الرسمية والحصص

| الترحيل | المحتوى |
| --- | --- |
| `16_000001_create_role_drive_quotas_table` | الحصص + قيم أولية |
| `16_000002_rename_internal_correspondences_to_messages` | `correspondences` ← `messages` |
| `16_000003_create_formal_correspondences_tables` | `external_entities` + المراسلات + الأحداث |
| `16_000004` | مرفقات الردود وحالة القراءة المنفصلة |
| `16_000005_backfill_existing_message_read_state` | **تعبئة بيانات** للسجلات القديمة |
| `16_000006_create_formal_correspondence_documents_table` | الوثائق |
| `16_000007_add_first_place_to_formal_correspondences` | `first_place` |
| `19_000001–000008` | أرقام الكتب، صلاحيات الحذف والديوان، التوجيه، التوقيع الرقمي |

### 23 يونيو — 2 أغسطس — التنظيف والموارد البشرية

| الترحيل | المحتوى |
| --- | --- |
| `06_23_000001_drop_project_master_tables` | **حذف** جداول ميزة ملغاة |
| `07_19_000001_add_task_delete_with_activities_permission` | صلاحية جديدة |
| `07_27_000001_revamp_formal_correspondence_flow` | إعادة هيكلة + **تعبئة بيانات** |
| `07_28_000001_move_letter_into_processing` | نقل الكتاب داخل المعالجة |
| `07_29_000001_create_hr_module` | ثلاثة جداول + ثلاث صلاحيات |
| `07_29_000002_route_hr_requests_to_hr_first` | **إلغاء محطة رئيس القسم** |
| `07_29_000003_restrict_hr_to_hr_department` | حصر HR بقسمه |
| `07_30_000001_add_documents_to_hr_requests` | أعمدة الوثيقة المولّدة |
| `08_02_000001_grant_hr_view_to_general_manager` | منح صلاحية |

---

## أنواع الترحيلات في هذا المشروع

### 1. إنشاء جدول

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('branches'); }
};
```

### 2. تعديل جدول

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('role')->default('employee');
        $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
        $table->string('api_token', 64)->nullable()->unique();
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropConstrainedForeignId('branch_id');
        $table->dropColumn(['role', 'api_token']);
    });
}
```

> `dropConstrainedForeignId()` يحذف القيد والعمود معاً. `dropColumn()` وحده يفشل
> على عمود له مفتاح أجنبي في PostgreSQL.

### 3. منح صلاحيات

```php
public const PERMISSIONS = [
    'hr.view' => [],                                    // لأشخاص لا لأدوار
    'hr.request' => ['database_manager', 'general_manager', 'employee', /* ... */],
];

private function grantPermissions(): void
{
    foreach (self::PERMISSIONS as $name => $roles) {
        $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        foreach ($roles as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }
    }

    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}
```

| النقطة | السبب |
| --- | --- |
| `firstOrCreate` | آمن للتكرار |
| `?->` بعد `first()` | الدور قد لا يوجد في بيئة ما |
| `forgetCachedPermissions()` | **إلزامي** — بدونه لا تُطبَّق الصلاحية |

### 4. تعبئة بيانات

من `2026_07_27_000001_revamp_formal_correspondence_flow.php`:

```php
DB::table('formal_correspondences')->orderBy('id')->chunkById(200, function ($rows) {
    foreach ($rows as $row) {
        // اشتقاق الجهة المصدرة والمخاطبة من الأعمدة القديمة
        DB::table('formal_correspondences')->where('id', $row->id)->update([...]);
    }
});
```

| النقطة | السبب |
| --- | --- |
| `DB::table()` لا Eloquent | **الموديل يتغير مع الزمن؛ الترحيل يجب أن يبقى صالحاً** |
| `chunkById(200)` | لا يحمّل آلاف الصفوف في الذاكرة |
| `orderBy('id')` | ترتيب مستقر للتقطيع |

> **قاعدة مهمة:** لا تستخدم موديلات Eloquent داخل الترحيلات. لو حُذف عمود لاحقاً
> أو تغيّر `$fillable`، سيفشل ترحيل قديم عند إعادة البناء من الصفر.

---

## كتابة ترحيل جديد

```powershell
cd BackEnd
php artisan make:migration create_assets_table
php artisan make:migration add_status_to_assets_table
```

Laravel 12 يستخدم أصنافاً مجهولة (`return new class extends Migration`) — اتبع النمط.

### قواعد التسمية في هذا المشروع

الترحيلات المولّدة تحمل طابعاً زمنياً دقيقاً، لكن ترحيلات المشروع اليدوية تتبع
`YYYY_MM_DD_00000N_وصف`. اتبع هذا النمط ليبقى الترتيب واضحاً.

### قواعد المفاتيح الأجنبية

| النمط | متى |
| --- | --- |
| `->constrained()->cascadeOnDelete()` | السجل الابن لا معنى له بلا الأب (تقرير بلا موظف) |
| `->constrained()->nullOnDelete()` | السجل يبقى ذا معنى (مهمة بلا مسؤول) |

### الفهارس

```php
$table->string('status')->default('planned')->index();   // يُستخدم في where
$table->unique(['user_id', 'year']);                      // قيد فرادة مركّب
$table->string('code')->unique();
```

افهرس كل عمود يظهر في `where` أو `orderBy` كثيراً — خصوصاً `status` و`type`.

---

## التشغيل

```powershell
php artisan migrate                       # تنفيذ الجديد
php artisan migrate --seed                # مع البذور
php artisan migrate --force               # في الإنتاج (بلا تأكيد)
php artisan migrate:status                # الحالة
php artisan migrate:rollback              # تراجع دفعة واحدة
php artisan migrate:rollback --step=3     # تراجع 3 ترحيلات
php artisan migrate:fresh --seed          # ⚠ حذف كل الجداول وإعادة البناء
```

> `migrate:fresh` **يحذف كل البيانات**. لا تستخدمه إلا في التطوير.

---

## قائمة تحقق قبل دمج ترحيل

- [ ] `up()` و`down()` كلاهما صحيح
- [ ] `migrate` ثم `migrate:rollback` يعملان بلا خطأ
- [ ] `migrate:fresh --seed` ينجح من الصفر
- [ ] لا موديلات Eloquent داخل الترحيل
- [ ] تعبئة البيانات تستخدم `chunkById`
- [ ] المفاتيح الأجنبية لها سلوك حذف مناسب
- [ ] الأعمدة كثيرة الاستخدام في الفلترة مفهرسة
- [ ] منح الصلاحيات يستدعي `forgetCachedPermissions()`
- [ ] الترحيل يعمل على PostgreSQL **و**SQLite (الاختبارات)
- [ ] الوثائق محدَّثة في `04-database/03` أو `04`
