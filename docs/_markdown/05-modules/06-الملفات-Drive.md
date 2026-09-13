# وحدة Drive — الملفات والمجلدات

**الوحدة الأكبر:** `DriveController.php` بـ776 سطراً.

## الملفات

```
BackEnd/app/Modules/Drive/
├── Routes/api.php
├── Controllers/DriveController.php          (776 سطراً ⚠ المنطق كله)
└── Resources/ DriveFileResource, DriveFolderResource

FrontEnd/src/Page/Drive/
├── index.jsx                                (589 سطراً ⚠)
└── components/
    ├── DriveComponents.jsx                  (1014 سطراً ⚠ الأكبر)
    └── driveUtils.jsx
```

## المسارات

| الطريقة | المسار | مصادقة |
| --- | --- | :-: |
| GET | `/api/public-files/{token}` | — |
| GET | `/api/public-folders/{token}` | — |
| GET | `/api/drive-files` | ✔ |
| POST | `/api/drive-files` | ✔ |
| POST | `/api/drive-folders` | ✔ |
| POST | `/api/drive-archive` | ✔ |
| GET/PUT | `/api/drive-role-quotas` | ✔ (مدير قواعد البيانات) |
| POST/DELETE | `/api/drive-files/{file}/share` | ✔ |
| POST/DELETE | `/api/drive-folders/{folder}/share` | ✔ |
| POST/DELETE | `/api/drive-files/{file}/public-link` | ✔ |
| POST/DELETE | `/api/drive-folders/{folder}/public-link` | ✔ |
| GET | `/api/drive-files/{file}/download` | ✔ |
| GET | `/api/drive-files/{file}/preview` | ✔ |
| DELETE | `/api/drive-files/{file}` | ✔ |
| DELETE | `/api/drive-folders/{folder}` | ✔ |

---

## المبدأ المميز — مجلدات فيزيائية حقيقية

خلافاً للمرفقات (تُخزَّن بأسماء عشوائية)، Drive **ينشئ مجلدات حقيقية ويحفظ الملفات
بأسمائها الأصلية**:

```
storage/app/private/drive/users/{user_id}/
├── مشاريع 2026/
│   ├── المخطط.pdf
│   ├── المخطط (2).pdf        ← عند تكرار الاسم
│   └── تقارير/
│       └── يناير.docx
```

> **السبب:** ليتمكن مدير النظام من تصفح الملفات من نظام التشغيل ويرى ما يراه
> المستخدم في الواجهة تماماً. عمود `path` يحفظ المسار الفيزيائي.

---

## النطاقات الأربعة

| النطاق | المعنى | من ينشئه |
| --- | --- | --- |
| `personal` | خاص بالمستخدم | الجميع |
| `department` | مشترك في القسم | من له وصول للقسم |
| `task` | مرفق بمهمة | من له وصول للمهمة |
| `organization` | كل المؤسسة | **مدير قواعد البيانات فقط** |

```php
if ($data['scope'] === 'organization')
    abort_unless($user->primaryRole() === 'database_manager', 403);
```

---

## قاعدة الوراثة

```php
$scope        = $folder?->scope ?? $data['scope'];
$departmentId = $folder?->department_id ?? ($data['department_id'] ?? $user->department_id);
```

**الملف والمجلد الفرعي يرثان من الأب:** النطاق، القسم، والنشر العام.

```php
if ($folder?->public_token) $this->publishFile($file);      // رفع في مجلد منشور ← نشر تلقائي
if ($parent?->public_token) $this->publishFolder($folder);
```

> رفع ملف داخل مجلد منشور **ينشره تلقائياً**. هذا مقصود ليتصرف Drive كنظام ملفات
> حقيقي — لكنه مفاجئ. نبّه المستخدم في الواجهة.

---

## حدود الرفع

```php
private const MAX_UPLOAD_FILES = 100;
private const MAX_UPLOAD_FILE_KILOBYTES = 102400;   // 100 ميغابايت
private const MAX_UPLOAD_TOTAL_BYTES = 1073741824;  // 1 غيغابايت

abort_if($incomingSize > self::MAX_UPLOAD_TOTAL_BYTES, 422,
    'الحجم الإجمالي للملفات في عملية الرفع الواحدة يجب ألا يتجاوز 1GB.');
```

> حدود PHP نفسها قد تكون أقل. `GET /api/status` يعرضها في `upload_limits`.

## حصص التخزين

جدول `role_drive_quotas` — `quota_bytes = null` يعني بلا حد.

| الدور | الحصة |
| --- | --- |
| `employee`, `technician`, `department_head`, `branch_manager`, `general_manager` | 3 غيغابايت |
| `database_manager` | بلا حد |

```php
public function updateRoleQuotas(Request $request): JsonResponse
{
    abort_unless($request->user()->primaryRole() === 'database_manager', 403);
    abort_unless(Schema::hasTable('role_drive_quotas'), 500, 'يجب تنفيذ ترحيل ... أولاً.');

    foreach ($data['quotas'] as $quota) {
        if ($quota['role'] === 'database_manager') continue;    // ★ لا يُقيَّد
        DB::table('role_drive_quotas')->updateOrInsert(['role' => $quota['role']], [...]);
    }
}
```

> تخطّي `database_manager` صريح — لا يستطيع أحد تقييد مدير قواعد البيانات،
> وهو الوحيد الذي يستطيع تعديل الحصص أصلاً.

`ensureQuotaAllowsUpload()` يفحص قبل كل رفع.

---

## المشاركة

### ثلاثة أهداف

```php
$data = $request->validate([
    'target' => ['required', Rule::in(['users', 'department', 'branch'])],
    'user_ids' => ['nullable', 'array'],
    'department_id' => ['nullable', 'integer', 'exists:departments,id'],
    'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
]);

$query = $this->recipientQuery($request->user())->where('is_active', true);
if ($data['target'] === 'users')      $query->whereIn('id', $data['user_ids'] ?? []);
if ($data['target'] === 'department') $query->where('department_id', $data['department_id'] ?? 0);
if ($data['target'] === 'branch')     $query->where('branch_id', $data['branch_id'] ?? 0);

$ids = $query->whereKeyNot($request->user()->id)->pluck('id');
$file->sharedUsers()->syncWithoutDetaching(
    $ids->mapWithKeys(fn ($id) => [$id => ['shared_by' => $request->user()->id]])->all()
);
```

| النقطة | التفصيل |
| --- | --- |
| `recipientQuery()` | يحصر المستلمين بنطاق المشارِك — **لا يشارك مع من لا يراه** |
| `where('is_active', true)` | لا مشاركة مع حسابات معطّلة |
| `whereKeyNot($user->id)` | لا يشارك مع نفسه |
| `syncWithoutDetaching` | **يضيف ولا يحذف** المشاركات السابقة |
| `?? 0` | معرّف غائب ← لا نتائج (لا الكل) |

مشاركة **المجلد تُظهر كل محتوياته**.

---

## الروابط العامة — نوعان

| النوع | الشكل | المميزات |
| --- | --- | --- |
| `public_url` | `/api/public-files/{token}` | توكن عشوائي قابل للإلغاء، يمرّ بالخادم |
| `direct_url` | رابط ثابت في `storage/drive-public/...` | يعمل من أي برنامج خارجي، بنية مجلدات مطابقة |

عند النشر تُنشأ **مرآة فعلية** في `storage/app/public/drive-public` بنفس البنية والأسماء.

```
storage/app/private/drive/users/3/مشاريع/خطة.pdf     ← الأصل الخاص
storage/app/public/drive-public/مشاريع/خطة.pdf        ← المرآة العامة
```

> إلغاء النشر يحذف **النسخة العامة فقط** ويُبقي الأصل الخاص سليماً.

---

## التنزيل والمعاينة والأرشيف

| المسار | الاستجابة | الاستخدام |
| --- | --- | --- |
| `/drive-files/{file}/download` | `BinaryFileResponse` | تنزيل |
| `/drive-files/{file}/preview` | `StreamedResponse` | عرض داخل المتصفح (صور، PDF) |
| `POST /drive-archive` | `BinaryFileResponse` | ZIP لعدة ملفات ومجلدات |

الأرشيف يقبل `file_ids` و`folder_ids`، ويُفلتر بـ`visibleFiles($user)` — **لا يمكن
تنزيل ما لا تراه حتى بتمرير معرّفات صريحة**.

الواجهة تستخدم `requestBlobProgress()` لعرض مؤشر تقدّم أثناء بناء الأرشيف.

---

## التسلسل الكامل للرفع

```
1. التحقق (عدد الملفات، حجم كل ملف، النطاق)
2. فحص الحجم الإجمالي            ← abort 422
3. فحص نطاق المؤسسة               ← abort 403
4. تحميل المجلد الأب وفحص التحكم به
5. اشتقاق النطاق والقسم بالوراثة
6. فحص الوصول للقسم               ← abort 403
7. فحص المهمة إن وُجدت
8. فحص الحصة                     ← ensureQuotaAllowsUpload()
9. لكل ملف:
     ├─ إنشاء المجلد الفيزيائي
     ├─ توليد اسم فريد ( (2), (3) )
     ├─ التخزين على قرص local
     ├─ إنشاء سجل DriveFile
     ├─ تسجيل حركة إن كان مرتبطاً بمهمة
     └─ نشر تلقائي إن كان المجلد منشوراً
```

---

## دوال الفحص الداخلية

| الدالة | تفحص |
| --- | --- |
| `authorizeControl($user, $file)` | هل يتحكم بالملف (رفعه أو يملك نطاقه)؟ |
| `authorizeControlFolder($user, $folder)` | هل يتحكم بالمجلد؟ |
| `canAccessDepartment($user, $id)` | هل القسم ضمن نطاقه؟ |
| `visibleFiles($user)` | استعلام الملفات المرئية |
| `recipientQuery($user)` | من يجوز المشاركة معهم |
| `ensureQuotaAllowsUpload($user, $size)` | الحصة تكفي؟ |
| `uniqueName()` / `uniqueDirectoryName()` | اسم غير مكرّر |
| `ensureFolderPath($folder)` | المسار الفيزيائي موجود |
| `publishFile()` / `publishFolder()` | إنشاء المرآة العامة |

---

## ملاحظة على الحجم

`DriveController` (776 سطراً) و`DriveComponents.jsx` (1014 سطراً) يتجاوزان بكثير
حد 400 سطر.

**عند التعديل، استخرج بدل التوسيع.** التقسيم المقترح للباك أند:

| الخدمة المقترحة | تستقبل |
| --- | --- |
| `DriveStorageService` | إنشاء المجلدات، الأسماء الفريدة، المسارات |
| `DriveShareService` | المشاركة والإلغاء |
| `DrivePublishService` | الروابط العامة والمرآة |
| `DriveQuotaService` | الحصص |
| `DriveArchiveService` | بناء ZIP |

---

## نقاط التوسّع

| ما تريد إضافته | كيف |
| --- | --- |
| البحث داخل محتوى الملفات | OCR في Job خلفي + عمود نص مفهرس |
| نسخ متعددة للملف | جدول `drive_file_versions` |
| سلة محذوفات | `softDeletes()` + شاشة استعادة |
| مشاركة بصلاحية قراءة/تعديل | عمود `permission` في جداول المشاركة |
| رابط عام بتاريخ انتهاء | عمود `expires_at` + فحص في `publicDownload()` |
| معاينة Office | تحويل إلى PDF عبر `DocxPdfConverter` |
| تنبيه عند اقتراب الحصة | فحص في `ensureQuotaAllowsUpload()` + إشعار |
