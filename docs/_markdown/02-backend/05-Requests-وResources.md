# Requests و Resources

هما طرفا العقد بين الخادم والواجهة: `Request` يحكم ما يدخل، و`Resource` يحكم ما يخرج.

---

## Form Requests — التحقق من المدخلات

### البنية

`app/Modules/Reports/Requests/StoreReportRequest.php`:

```php
class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reports.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'type'         => ['required', 'in:daily_report,daily_plan,weekly_report,weekly_plan'],
            'period_start' => ['required', 'date'],
            'period_end'   => ['nullable', 'date'],
            'title'        => ['required', 'max:255'],
            'summary'      => ['required', 'string'],
            'achievements' => ['nullable', 'string'],
            'challenges'   => ['nullable', 'string'],
            'next_steps'   => ['nullable', 'string'],
        ];
    }
}
```

### `authorize()` — طبقة حماية ثانية

المسار يفرض `permission:reports.create` أصلاً، والـRequest يفحصها ثانيةً.
التكرار مقصود: لو نُقل المسار أو نُسي الـmiddleware يبقى الحاجز قائماً.

مثال أعقد — `TransitionReportRequest.php`:

```php
public function authorize(): bool
{
    return ($this->user()?->can('reports.transition') ?? false)
        || $this->user()?->primaryRole() === 'database_manager';
}
```

استثناء صريح لمدير قواعد البيانات، لأنه يملك صلاحيات انتقال كاملة على كل التقارير.

> `?->` و`?? false` مقصودان: عند غياب المستخدم لا يُرمى استثناء بل تُرفض العملية.

### القواعد الشرطية

```php
'note' => ['required_if:action,return', 'nullable', 'max:2000'],
```

الملاحظة إلزامية عند الإعادة فقط. القاعدة نفسها مكرّرة دفاعياً في
`ReportRepository::ensureAllowedTransition()`:

```php
if ($action === 'return' && blank($note)) {
    throw ValidationException::withMessages(['note' => __('messages.validation_required')]);
}
```

### قواعد شائعة في المشروع

| القاعدة | الاستخدام |
| --- | --- |
| `in:a,b,c` | الحقول المحصورة بقيم (الحالة، النوع، الأولوية) |
| `exists:users,id` | المفاتيح الأجنبية |
| `unique:branches,code` | الرموز الفريدة |
| `nullable` | الحقول الاختيارية — **بدونها يفشل التحقق عند إرسال `null`** |
| `date` / `date_format:H:i` | التواريخ والأوقات |
| `file` / `mimes:` / `max:` | المرفقات (`max` بالكيلوبايت) |
| `array` / `<field>.*` | المصفوفات المتشعّبة |

### الحقول المتشعّبة

بيانات الموظف الاختيارية تُرسل متشعّبة — انظر
`Modules/Employees/Requests/StoreEmployeeRequest.php`:

```php
'address'          => ['nullable', 'array'],
'address.city'     => ['nullable', 'string', 'max:120'],
'personal_details' => ['nullable', 'array'],
'personal_details.blood_type' => ['nullable', 'string', 'max:10'],
```

### استجابة الفشل

Laravel يرجع 422 تلقائياً:

```json
{
  "message": "The given data was invalid.",
  "errors": { "title": ["The title field is required."] }
}
```

`FrontEnd/src/Api/http.js` يقرأ `data.message` ويرميه كـ`Error`، فيُعرض عبر
`Components/Feedback.jsx`.

### الطلبات الموجودة

| الموديول | الملفات |
| --- | --- |
| Core | `LoginRequest` |
| Reports | `StoreReportRequest`, `TransitionReportRequest`, `UpdateReturnedReportRequest` |
| Employees | `StoreEmployeeRequest`, `UpdateEmployeeRequest`, `UpdateOwnDetailsRequest` |
| Organization | `StoreBranchRequest`, `UpdateBranchRequest`, `StoreDepartmentRequest`, `UpdateDepartmentRequest` |
| Offices | `StoreOfficeRequest`, `UpdateOfficeRequest` |
| Permissions | `SyncRolePermissionsRequest` |
| Hr | `StoreHrRequestRequest` |
| Communications | `StoreMessageRequest`, `StoreMessageReplyRequest`, `StoreCircularRequest`, `StoreCorrespondenceRequest`, `StoreCorrespondenceReplyRequest`, `StoreFormalCorrespondenceRequest`, `StoreFormalCorrespondenceEventRequest`, `UpdateFormalCorrespondenceEventRequest` |

`Drive`, `Tasks`, `Forms` تتحقق داخل المتحكم عبر `$request->validate([...])` —
مقبول للحقول القليلة، لكن الأفضل في الكود الجديد هو `FormRequest`.

---

## Resources — تشكيل المخرجات

### إلغاء الغلاف

`app/Providers/AppServiceProvider.php`:

```php
public function register(): void
{
    JsonResource::withoutWrapping();
}
```

بدونه ترجع كل الاستجابات داخل `{"data": ...}`. مع الإلغاء يقرأ الفرونت الاستجابة
مباشرةً — لهذا `api.js` يكتب `.then(r => r)` لا `.then(r => r.data)`.

### المثال — `Modules/Reports/Resources/ReportResource.php`

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'type' => $this->type,
        'title' => $this->title,
        'status' => $this->status,
        'period_start' => $this->period_start?->toDateString(),
        'employee'   => new UserResource($this->whenLoaded('employee')),
        'branch'     => $this->whenLoaded('branch'),
        'department' => $this->whenLoaded('department'),
        'actions'    => $this->whenLoaded('actions'),
        'created_at' => $this->created_at?->toIso8601String(),
        'return_note' => $this->status === 'returned'
            ? Cache::get("reports:return-note:{$this->id}")
            : null,
    ];
}
```

ثلاث تقنيات مهمة هنا:

| التقنية | الفائدة |
| --- | --- |
| `whenLoaded('employee')` | لا يظهر الحقل إن لم تُحمّل العلاقة — يمنع مشكلة N+1 الصامتة |
| `?->toDateString()` | تنسيق موحّد للتواريخ مع أمان ضد `null` |
| `Cache::get(...)` | **جلب بيانات من خارج الجدول** — سبب الإعادة محفوظ في الكاش لا في قاعدة البيانات |

> `return_note` استثناء واعٍ لقاعدة "الـResource لا يجلب بيانات". السبب أن الملاحظة
> مؤقتة (14 يوماً) وتُحذف عند إعادة الإرسال، فلا تستحق عموداً دائماً.

### المثال الأغنى — `Modules/Employees/Resources/UserResource.php`

```php
$role = $this->getRoleNames()->first() ?? $this->role;

return [
    'id' => $this->id, 'name' => $this->name, 'email' => $this->email,
    'role'  => $role,
    'roles' => $this->getRoleNames(),
    'permissions' => $this->getAllPermissions()->pluck('name'),
    /* ... */
    'digital_signature_url' => $this->digital_signature_path
        ? Storage::disk('public')->url($this->digital_signature_path) : null,
    'can_manage_digital_signature' =>
        in_array($role, ['department_head','branch_manager','general_manager'], true)
        || filled($this->office_id),
    'address' => $this->whenLoaded('address'),
    'family_details' => $this->whenLoaded('familyDetails'),
    'personal_details' => $this->whenLoaded('personalDetails'),
];
```

نقاط جوهرية:

- **`permissions` تُرسل كاملة إلى الواجهة.** هذا ما يبني عليه `AppShell.jsx` وقوائم
  `navigation.json` قرار إظهار العناصر. الإظهار تحسين تجربة؛ الحماية في الخادم.
- **`can_manage_digital_signature` منطق محسوب في الـResource** — الواجهة لا تكرّر الشرط.
- **`password` و`api_token` لا يظهران** لأنهما في `$hidden` بالموديل، ولأن الـResource
  لا يذكرهما أصلاً — حاجزان.

### الروابط المحسوبة في الموديلات

بعض الموديلات تحسب الروابط بنفسها عبر `$appends`، مثل
`app/Models/FormalCorrespondenceDocument.php`:

```php
protected $appends = ['attachment_url', 'pdf_url'];

private function urlWithVersion(string $path): string
{
    $url = Storage::disk('public')->url($path);
    $version = $this->updated_at?->timestamp ?: now()->timestamp;
    return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $version;
}
```

> **لماذا `?v=timestamp`؟** الوثيقة قد تُعاد توليدها بنفس الاسم. بدون الوسم يخدم
> المتصفح النسخة القديمة من الكاش. الوسم يجبره على إعادة الجلب بعد كل تعديل.
> النمط نفسه في `FormalCorrespondenceEvent.php` و`HrRequest.php`.

### الموارد الموجودة

| الموديول | الموارد |
| --- | --- |
| Employees | `UserResource` |
| Reports | `ReportResource` |
| Organization | `BranchResource`, `DepartmentResource` |
| Tasks | `TaskResource` |
| Drive | `DriveFileResource`, `DriveFolderResource` |
| Forms | `CustomFormResource`, `CustomFormFieldResource`, `CustomFormPublicationResource`, `CustomFormSubmissionResource` |
| Communications | `CircularResource` |
| Notifications | `NotificationResource` |

`Hr` لا يستخدم Resources — يرجع الموديلات مباشرةً معتمداً على `$appends` و`$hidden`.
مقبول لكنه يجعل العقد ضمنياً؛ فضّل Resource صريحاً في الكود الجديد.

---

## أخطاء شائعة

| الخطأ | الأثر | الصواب |
| --- | --- | --- |
| نسيان `nullable` | فشل التحقق عند إرسال `null` | أضفها لكل حقل اختياري |
| `$this->employee->name` في Resource | استعلام لكل صف (N+1) | `whenLoaded()` + `with()` في الاستعلام |
| إرجاع الموديل مباشرة | تسريب أعمدة غير مقصودة | استخدم Resource |
| نسيان `?->` مع التواريخ | خطأ على القيم الفارغة | `$this->date?->toDateString()` |
| إضافة عمود دون تحديث Resource | لا يظهر في الواجهة | حدّث الـResource — هذا هو العقد |
| رابط ملف بلا `?v=` | المتصفح يعرض نسخة قديمة | اتبع نمط `urlWithVersion()` |
