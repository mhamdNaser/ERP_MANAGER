# مرجع الـAPI الكامل

كل المسارات تحمل بادئة `/api`. القائمة مولّدة من ملفات
`BackEnd/app/Modules/*/Routes/api.php`.

لعرض القائمة الحيّة:

```powershell
cd BackEnd
php artisan route:list --path=api
```

---

## قواعد عامة

### الترويسات

| الترويسة | القيمة | إلزامية |
| --- | --- | --- |
| `Authorization` | `Bearer <token>` | للمسارات المصادَقة |
| `Accept` | `application/json` | نعم |
| `Content-Type` | `application/json` | للطلبات ذات الجسم (يُحذف مع `FormData`) |
| `X-Language` | `ar` \| `en` | اختيارية (افتراضي من `.env`) |

### رموز الاستجابة

| الرمز | المعنى |
| --- | --- |
| 200 | نجاح |
| 201 | إنشاء |
| 401 | توكن مفقود/منتهٍ أو حساب معطّل |
| 403 | صلاحية ناقصة |
| 404 | غير موجود |
| 413 | الحمولة أكبر من حد PHP |
| 422 | فشل تحقق أو قاعدة عمل |
| 500 | خطأ غير متوقع (مع `error_id`) |
| 503 | قاعدة البيانات أو التخزين غير متاح |

**لا يوجد غلاف `data`** — `JsonResource::withoutWrapping()` مفعّل.

---

## Core — النواة

`Modules/Core/Routes/api.php`

| الطريقة | المسار | مصادقة | الصلاحية | الدالة |
| --- | --- | :-: | --- | --- |
| GET | `/status` | ✗ | — | `SystemController::health` |
| GET | `/system/health` | ✗ | — | `SystemController::health` |
| POST | `/auth/login` | ✗ | — | `SystemController::login` |
| GET | `/auth/me` | ✔ | — | `SystemController::me` |
| POST | `/auth/logout` | ✔ | — | `SystemController::logout` |
| GET | `/dashboard` | ✔ | — | `SystemController::dashboard` |

**تسجيل الدخول** — الجسم: `{ "email": "...", "password": "..." }`
الاستجابة: `{ "token": "...", "user": { ... } }`

---

## Locale — اللغة

`Modules/Locale/Routes/api.php`

| الطريقة | المسار | مصادقة | الدالة |
| --- | --- | :-: | --- |
| GET | `/locale/{lang}` | ✗ | `LocaleController::setLocale` — `lang` ∈ {`ar`,`en`} |
| GET | `/active-languages` | ✗ | `LocaleController::active` |

---

## Organization — الهيكل التنظيمي

`Modules/Organization/Routes/api.php` — جميعها `cnd.auth`

| الطريقة | المسار | الصلاحية | الدالة |
| --- | --- | --- | --- |
| GET | `/organization` | `organization.view` | `index` — القوائم **كاملة** |
| GET | `/organization/branches` | `organization.view` | `branches` — مرقَّمة، فلاتر `search`, `per_page`, `page` |
| GET | `/organization/departments` | `organization.view` | `departments` — مرقَّمة، الفلاتر نفسها |
| POST | `/branches` | `branches.create` | `storeBranch` |
| PUT | `/branches/{branch}` | `branches.update` | `updateBranch` |
| DELETE | `/branches/{branch}` | `branches.delete` | `destroyBranch` |
| POST | `/departments` | `departments.create` | `storeDepartment` |
| PUT | `/departments/{department}` | `departments.update` | `updateDepartment` |
| DELETE | `/departments/{department}` | `departments.delete` | `destroyDepartment` |

**القائمة الكاملة مقابل المرقَّمة:** `/organization` يعيد كل الأفرع والأقسام —
تحتاجها القوائم المنسدلة في نماذج التحرير وشريط فلاتر الموظفين. أما
`/organization/branches` و`/organization/departments` فللعرض في الشاشة:

```json
{ "data": [ … ], "meta": { "current_page": 1, "last_page": 4, "per_page": 5, "total": 18 } }
```

البحث في الأفرع على الاسم والرمز، وفي الأقسام على الاسم والرمز **واسم الفرع**.
المقارنة غير حسّاسة لحالة الأحرف، و`per_page` مسقوف بـ100.

---

## Offices — المكاتب

`Modules/Offices/Routes/api.php` — جميعها `offices.manage`

| الطريقة | المسار | الدالة |
| --- | --- | --- |
| GET | `/offices` | `index` |
| POST | `/offices` | `store` |
| PUT | `/offices/{office}` | `update` |
| DELETE | `/offices/{office}` | `destroy` |

---

## Employees — الموظفون

`Modules/Employees/Routes/api.php`

| الطريقة | المسار | الصلاحية | الدالة |
| --- | --- | --- | --- |
| GET | `/employees` | `employees.view` | `index` — كاملة، أو مرقَّمة بـ`per_page` |
| POST | `/employees` | `employees.create` | `store` |
| PUT | `/employees/{employee}` | `employees.update` | `update` |
| PUT | `/employees/{employee}/password` | `employees.update` | `updatePassword` |
| DELETE | `/employees/{employee}` | `employees.delete` | `destroy` |
| GET | `/profile/details` | — | `profile` |
| PUT | `/profile/details` | — | `updateProfile` |
| POST | `/profile/signature` | — | `updateSignature` |

**قائمة الموظفين** — بلا `per_page` تُعاد كاملةً كما كانت (تعتمد عليها قوائم
منسدلة في شاشات أخرى). ومع `per_page` تُرقَّم ويُرفق بها `meta`، وتقبل الفلاتر:

| المعامل | القيم |
| --- | --- |
| `search` | الاسم · البريد · المسمى الوظيفي · الرقم الذاتي |
| `branch_id` · `department_id` · `office_id` | معرّف |
| `role` | `employee` · `technician` · `department_head` · `branch_manager` · `general_manager` · `database_manager` · `office_manager` |
| `employment_type` | `fixed` · `contract` |
| `status` | `active` · `inactive` |

الفلاتر تُضيّق نطاق المستخدم ولا توسّعه: نطاقه مطبَّق قبلها في
`OrganizationScopeService`.

**التوقيع** — الجسم: `{ "signature_data": "data:image/png;base64,..." }`

---

## Reports — التقارير

`Modules/Reports/Routes/api.php`

| الطريقة | المسار | الصلاحية | الدالة |
| --- | --- | --- | --- |
| GET | `/reports` | `reports.view` | `index` |
| POST | `/reports` | `reports.create` | `store` |
| PUT | `/reports/{report}` | — | `update` |
| POST | `/reports/{report}/transition` | — | `transition` |

**الانتقال** — الجسم:

```json
{ "action": "submit|forward_branch|forward_general|return|approve",
  "note": "إلزامية عند return" }
```

---

## Tasks — المهام

`Modules/Tasks/Routes/api.php` — بلا صلاحيات (النطاق يحمي)

| الطريقة | المسار | الدالة |
| --- | --- | --- |
| GET | `/tasks` | `index` — يقبل `?department_id=` و`?all=1`، ويعيد اللوحة كاملة |
| GET | `/task-activities` | `activities` — يقبل فلاتر |
| POST | `/tasks` | `store` |
| PUT | `/tasks/{task}` | `update` |
| PATCH | `/tasks/{task}/move` | `move` — `{ "status": "...", "position": 0 }` |
| DELETE | `/tasks/{task}` | `destroy` |

> `GET /tasks` **لا يقبل فلاتر**. يعيد كل مهام النطاق دفعةً واحدة مع
> `members` و`departments`، وتتولى الواجهة التصفية والبحث محلياً — انظر
> `05-modules/05-المهام.md`. أي مستهلك آخر للمسار عليه أن يتوقع اللوحة كاملة.

---

## Drive — الملفات

`Modules/Drive/Routes/api.php`

### عامة (بلا مصادقة)

| الطريقة | المسار | الدالة |
| --- | --- | --- |
| GET | `/public-files/{token}` | `publicDownload` |
| GET | `/public-folders/{token}` | `publicFolder` |

### مصادَقة

| الطريقة | المسار | الدالة |
| --- | --- | --- |
| GET | `/drive-files` | `index` |
| POST | `/drive-files` | `store` — **multipart**: `files[]`, `scope`, `folder_id`… |
| POST | `/drive-folders` | `storeFolder` |
| POST | `/drive-archive` | `archive` — `{ "file_ids": [], "folder_ids": [] }` ← ZIP |
| GET | `/drive-role-quotas` | `roleQuotasIndex` (مدير قواعد البيانات) |
| PUT | `/drive-role-quotas` | `updateRoleQuotas` (مدير قواعد البيانات) |
| POST | `/drive-files/{file}/share` | `share` |
| POST | `/drive-folders/{folder}/share` | `shareFolder` |
| DELETE | `/drive-files/{file}/share` | `revokeShare` |
| DELETE | `/drive-folders/{folder}/share` | `revokeFolderShare` |
| POST | `/drive-files/{file}/public-link` | `publicLink` |
| DELETE | `/drive-files/{file}/public-link` | `revokePublicLink` |
| POST | `/drive-folders/{folder}/public-link` | `publicFolderLink` |
| DELETE | `/drive-folders/{folder}/public-link` | `revokePublicFolderLink` |
| GET | `/drive-files/{file}/download` | `download` |
| GET | `/drive-files/{file}/preview` | `preview` |
| DELETE | `/drive-files/{file}` | `destroy` |
| DELETE | `/drive-folders/{folder}` | `destroyFolder` |

**المشاركة** — الجسم:

```json
{ "target": "users|department|branch",
  "user_ids": [1,2], "department_id": 3, "branch_id": 1 }
```

**حدود الرفع:** 100 ملف، 100 ميغابايت للملف، 1 غيغابايت إجمالاً.

---

## Forms — الفورمات

`Modules/Forms/Routes/api.php`

| الطريقة | المسار | الصلاحية | الدالة |
| --- | --- | --- | --- |
| GET | `/forms` | — | `index` |
| POST | `/forms` | `forms.manage` | `store` |
| PUT | `/forms/{form}` | `forms.manage` | `update` |
| DELETE | `/forms/{form}` | `forms.manage` | `destroy` |
| POST | `/forms/{form}/publish` | — | `publish` |
| POST | `/forms/publications/{publication}/submit` | — | `submit` |
| GET | `/forms/{form}/submissions` | — | `submissions` |
| GET | `/forms/users/{user}` | — | `userHistory` |

---

## Communications — الرسائل والمراسلات والتعاميم

`Modules/Communications/Routes/api.php`

### الرسائل الداخلية

| الطريقة | المسار | الصلاحية |
| --- | --- | --- |
| GET | `/communication-directory` | `messages.create\|correspondences.create` |
| GET | `/messages` | `messages.view\|correspondences.view` |
| GET | `/messages/unread-count` | `messages.view\|…` |
| POST | `/messages` | `messages.create\|…` |
| PATCH | `/messages/{message}/read` | `messages.view\|…` |
| POST | `/messages/{message}/replies` | `messages.reply\|correspondences.reply` |
| DELETE | `/messages/{message}` | `messages.view\|…` |

### المراسلات الرسمية

| الطريقة | المسار | الصلاحية |
| --- | --- | --- |
| GET | `/formal-correspondences` | `formal_correspondences.view` |
| GET | `/formal-correspondences-directory` | `.create\|.route` |
| POST | `/formal-correspondences` | `.create` |
| POST | `/formal-correspondences/{formalCorrespondence}` | `.create` (تحديث) |
| DELETE | `/formal-correspondences/{formalCorrespondence}` | `.view` |
| POST | `/formal-correspondences/{formalCorrespondence}/events` | `.route` |
| PUT | `/formal-correspondences/events/{event}` | `.route` |
| POST | `/formal-correspondences/events/{event}/decision` | `.route` |
| POST | `/formal-correspondences/events/{event}/response` | `.route` |
| POST | `/formal-correspondences/events/{event}/assign` | `.route` |
| POST | `/formal-correspondences/events/{event}/documents` | `.route` |
| DELETE | `/formal-correspondences/events/{event}` | `.route` |
| POST | `/formal-correspondences/documents/{document}` | `.route` |

### التعاميم

| الطريقة | المسار | الصلاحية |
| --- | --- | --- |
| GET | `/circulars` | `circulars.view` |
| GET | `/circulars/{circular}` | `circulars.view` |
| POST | `/circulars` | `circulars.create` |

---

## Notifications — الإشعارات

`Modules/Notifications/Routes/api.php` — جميعها `notifications.view`

| الطريقة | المسار | الدالة |
| --- | --- | --- |
| GET | `/notifications` | `index` |
| GET | `/notifications/{notification}` | `show` |
| POST | `/notifications/read-all` | `readAll` |

---

## Hr — الموارد البشرية

`Modules/Hr/Routes/api.php`

| الطريقة | المسار | الصلاحية | الدالة |
| --- | --- | --- | --- |
| GET | `/hr/my-requests` | — | `mine` |
| POST | `/hr/requests` | `hr.request` | `store` |
| POST | `/hr/requests/{hrRequest}/decision` | — | `decide` |
| POST | `/hr/requests/{hrRequest}/cancel` | — | `cancel` |
| GET | `/hr/requests` | `hr.view` | `index` — فلاتر `type`, `status`, `user_id` |
| PUT | `/hr/balances/{employee}` | `hr.manage` | `updateBalance` |

**إنشاء طلب** — **multipart** (يقبل `attachment`):

```
type=leave|departure|document
subtype=annual|sick|unpaid|employment|salary
start_date, end_date        (leave)
start_time, end_time        (departure)
reason                      (إلزامي)
user_id                     (اختياري — HR فقط)
attachment                  (اختياري، ≤20MB)
```

**القرار** — `{ "action": "approve|reject", "note": "..." }`

**الرصيد** — `{ "annual_entitlement": 30, "used_days": 5 }`

> `mission` لم يعد نوعاً هنا منذ 1.0.0 — انظر قسم Fleet أدناه.

---

## Fleet — فرع الآليات

`Modules/Fleet/Routes/api.php`

| الطريقة | المسار | الصلاحية | الدالة |
| --- | --- | --- | --- |
| GET | `/fleet/my-missions` | — | `mine` — مهام الطالب + `is_fleet_staff` |
| POST | `/fleet/missions` | `fleet.request` | `store` |
| POST | `/fleet/missions/{fleetMission}/decision` | — | `decide` |
| POST | `/fleet/missions/{fleetMission}/cancel` | — | `cancel` |
| GET | `/fleet/missions` | `fleet.view` | `index` — فلاتر `status`, `user_id` |

**إنشاء مهمة** — **multipart** (يقبل `attachment`):

```
start_date, end_date        (إلزاميان)
destination                 (إلزامي، ≤180 حرفاً)
reason                      (إلزامي، ≤2000 حرف)
days                        (اختياري — يحسبها الخادم إن غابت)
user_id                     (اختياري — فرع الآليات فقط)
attachment                  (اختياري، ≤20MB)
```

**القرار** — `{ "action": "approve|reject", "note": "..." }`

`GET /fleet/missions` يعيد `missions` و`summary` و`is_fleet_staff`، ويعيد `employees`
لموظفي الفرع وحدهم. المدير العام يرى `pending_gm` و`approved` و`rejected` فقط.

---

## Permissions — الصلاحيات

`Modules/Permissions/Routes/api.php` — جميعها `roles.permissions.manage`

| الطريقة | المسار | الدالة |
| --- | --- | --- |
| GET | `/roles-permissions` | `index` |
| PUT | `/roles-permissions/{role}` | `update` — `{ "permissions": ["a","b"] }` |

---

## Templates — قوالب الوثائق

`Modules/Templates/Routes/api.php` — جميعها `templates.manage`

| الطريقة | المسار | الدالة |
| --- | --- | --- |
| GET | `/document-templates` | `index` — كل القوالب مع حقولها ونسخها السابقة |
| GET | `/document-templates/{key}/download` | `download` — القالب الحالي |
| GET | `/document-templates/{key}/blank` | `blank` — الأصل الورقي الفارغ إن وُجد |
| POST | `/document-templates/{key}` | `upload` — **multipart**: `template` (docx ≤20MB)، `force` |
| POST | `/document-templates/{key}/restore` | `restore` — `{ "backup": "2026-09-22_101500.docx" }` |

`{key}` بصيغة `مجموعة.اسم` مثل `hr.leave_request`، مقيَّدة بـ`[a-z_]+\.[a-z_]+`.

**رفع قالب ناقص الحقول** يعيد 422:

```json
{ "message": "القالب المرفوع ينقصه 3 حقل.", "missing": ["days", "reason", "start_date"], "requires_force": true }
```

أعد الرفع بـ`force=1` للقبول رغم النقص. الرفض لا يلمس الملف القائم.

---

## Database — النسخ الاحتياطي

`Modules/Database/Routes/api.php` — جميعها `database.backups.manage`

| الطريقة | المسار | الدالة |
| --- | --- | --- |
| GET | `/database-backups` | `index` |
| POST | `/database-backups/internal` | `internal` |
| POST | `/database-backups/external` | `external` (تنزيل مباشر) |
| GET | `/database-backups/{fileName}/download` | `download` |
| DELETE | `/database-backups/{fileName}` | `destroy` |

`{fileName}` مقيّد بـ`[A-Za-z0-9._-]+`.

---

## Broadcasting — البث

| الطريقة | المسار | ملاحظة |
| --- | --- | --- |
| POST | `/broadcasting/auth` | تصريح الاشتراك في `private-user.{id}` — يمرّ بـ`cnd.auth` |

الأحداث المبثوثة:

| الحدث | القناة |
| --- | --- |
| `.message.created` | `private-user.{recipient_id}` |
| `.message.reply.created` | `private-user.{recipient_id}` |

---

## أمثلة curl

```bash
BASE=http://127.0.0.1:8000/api

# الصحة
curl "$BASE/status"

# الدخول
TOKEN=$(curl -s -X POST "$BASE/auth/login" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"general@cnd.local","password":"password"}' | jq -r .token)

# قائمة مصادَقة
curl "$BASE/reports" -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' -H 'X-Language: ar'

# انتقال تقرير
curl -X POST "$BASE/reports/1/transition" \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"action":"approve"}'

# رفع ملف
curl -X POST "$BASE/drive-files" \
  -H "Authorization: Bearer $TOKEN" \
  -F 'files[]=@document.pdf' -F 'scope=personal'
```

> **لا ترسل `Content-Type` مع `-F`** — curl يضبط `multipart/form-data` والـ`boundary`.
