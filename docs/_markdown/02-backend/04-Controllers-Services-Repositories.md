# Controllers و Services و Repositories

## توزيع المسؤوليات

| الطبقة | تجيب على سؤال | يجب أن تبقى |
| --- | --- | --- |
| Controller | "ماذا طُلب مني؟" | نحيفة — تفويض فقط |
| Service | "ما قواعد العمل؟" | مستقلة عن HTTP |
| Repository | "كيف أصل للبيانات؟" | مستقلة عن قواعد العمل |

القاعدة العملية: **إذا احتوى المتحكم على `if` تفحص دوراً أو حالة، فمكانها خدمة.**

---

## المتحكمات

### النموذج المثالي — `Modules/Reports/Controllers/ReportController.php` (37 سطراً)

```php
class ReportController extends Controller
{
    public function __construct(private ReportRepositoryInterface $reports) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ReportResource::collection($this->reports->visibleTo($request->user()));
    }

    public function transition(TransitionReportRequest $request, Report $report): ReportResource
    {
        return new ReportResource(
            $this->reports->transition($request->user(), $report,
                $request->validated('action'), $request->validated('note'))
        );
    }
}
```

كل دالة سطر أو سطران. لا `if`، لا استعلامات، لا فحص صلاحيات.

### أحجام المتحكمات الفعلية

| المتحكم | الأسطر | ملاحظة |
| --- | --- | --- |
| `Organization/OrganizationController.php` | 31 | نموذجي |
| `Notifications/NotificationController.php` | 31 | نموذجي |
| `Reports/ReportController.php` | 37 | نموذجي |
| `Database/DatabaseBackupController.php` | 48 | جيد |
| `Communications/CommunicationController.php` | 62 | يحوي منطق رفع المرفقات |
| `Employees/EmployeeController.php` | 100 | مقبول |
| `Core/SystemController.php` | 115 | فحص الصحة مفصّل |
| `Forms/CustomFormController.php` | 141 | يفوّض لـ`CustomFormService` |
| `Hr/HrRequestController.php` | 164 | يفوّض لثلاث خدمات |
| `Tasks/TaskController.php` | 278 | المنطق داخله — مرشّح للتقسيم |
| `Communications/FormalCorrespondenceController.php` | 415 | ⚠ يتجاوز حد 400 سطر |
| `Drive/DriveController.php` | 776 | ⚠ الأكبر في المشروع |

> `DriveController` و`FormalCorrespondenceController` مرشّحان للتقسيم إلى خدمات.
> إن عدّلت أحدهما، استخرج ما تعدّله إلى خدمة بدل توسيع الملف. انظر
> `07-guides/02-قواعد-التطوير.md`.

### أنواع الإرجاع

| النوع | متى | مثال |
| --- | --- | --- |
| `JsonResource` | سجل واحد | `ReportController::store()` |
| `AnonymousResourceCollection` | قائمة عبر Resource | `ReportController::index()` |
| `JsonResponse` | استجابة مخصصة أو رمز حالة | `SystemController::health()` |
| `BinaryFileResponse` | تنزيل ملف | `DriveController::download()` |
| `StreamedResponse` | معاينة ملف بالتدفق | `DriveController::preview()` |

---

## الخدمات

### خدمات النطاق

ترجع `Builder` أو `bool`. لا تعدّل بيانات.

| الخدمة | الملف |
| --- | --- |
| `OrganizationScopeService` | `Modules/Organization/Services/` |
| `ReportAccessService` | `Modules/Reports/Services/` |
| `HrAccessService` | `Modules/Hr/Services/` |
| `FormalVisibilityService` | `Modules/Communications/Services/` |

### خدمات سير العمل

تنفّذ الانتقالات وتكتب السجلات وترسل الإشعارات.

**`Modules/Hr/Services/HrWorkflowService.php`** — النموذج الأوضح:

```php
public function approve(HrRequest $request, User $actor, ?string $note = null): HrRequest
{
    return DB::transaction(function () use ($request, $actor, $note) {
        $this->record($request, $actor, 'approve', $note);     // سجل القرار

        $next = $this->nextStage($request->stage);
        $request->update([
            'stage'      => $next ?: 'done',
            'status'     => $this->statusForStage($next),
            'decided_at' => $next ? null : now(),
        ]);

        if (! $next) {                                         // المحطة الأخيرة
            $this->consumeBalance($request);                   // خصم الرصيد
            $this->documents->generate($request->load('user'), $actor);  // توليد الوثيقة
            $this->notify(...);
            $this->notifyWatchers(...);
        } else {
            $this->notifyStageOwners($request);
            $this->notify(...);
        }

        return $request->fresh();
    });
}
```

خمس مسؤوليات في معاملة واحدة: السجل، الحالة، الرصيد، الوثيقة، الإشعارات.
لو فشلت أي واحدة تُلغى كلها.

**المحطات معرّفة كثابت** في `app/Models/HrRequest.php`:

```php
public const STAGES = ['hr', 'gm'];
```

و`nextStage()` يتنقل بينها بالفهرس. **لإضافة محطة ثالثة يكفي تعديل هذا الثابت**
(مع مواءمة `HrAccessService::canDecide()` و`stageLabel()`).

### خدمات الوثائق

| الخدمة | الملف | الدور |
| --- | --- | --- |
| `HrDocumentService` | `Modules/Hr/Services/` | يولّد وثيقة طلب HR |
| `FormalDocumentService` | `Modules/Communications/Services/` | يولّد كتاباً رسمياً |
| `DocxPdfConverter` | `Modules/Communications/Services/` | DOCX ← PDF |
| `SignatureImage` | `Modules/Communications/Services/` | تجهيز صورة التوقيع |
| `DocxTemplateRenderer` | `app/Services/Documents/` | محرك القوالب المشترك |
| `SimpleQrImage` | `app/Services/Documents/` | توليد QR |

التفاصيل في `06-توليد-Word-وPDF.md`.

### خدمات التحليل والمساعدة

| الخدمة | الملف | الدور |
| --- | --- | --- |
| `DashboardAnalyticsService` | `Modules/Core/Services/` | مؤشرات لوحة المعلومات |
| `TaskActivityService` | `Modules/Tasks/Services/` | تسجيل حركات المهام |
| `CustomFormService` | `Modules/Forms/Services/` | منطق الفورمات كاملاً (341 سطراً) |
| `FormalTimelineService` | `Modules/Communications/Services/` | بناء السلسلة الزمنية |
| `FormalPartyResolver` | `Modules/Communications/Services/` | تحديد الأطراف من النوع والمعرّف |
| `DatabaseBackupService` | `Modules/Database/Services/` | النسخ الاحتياطي |

---

## المستودعات

### النمط الكامل — أربعة ملفات

**1. العقد** `Modules/Reports/Repositories/Interfaces/ReportRepositoryInterface.php`

**2. التنفيذ** `Modules/Reports/Repositories/Eloquent/ReportRepository.php`

**3. الربط** `Modules/Reports/Providers/ReportsServiceProvider.php`

```php
public function register(): void
{
    $this->app->bind(ReportRepositoryInterface::class, ReportRepository::class);
}
```

**4. التسجيل** — أضف المزوّد إلى `BackEnd/bootstrap/providers.php`.

> **هذه الخطوة الرابعة تُنسى كثيراً.** المسارات تُحمّل تلقائياً بـ`glob`، أما
> المزوّدات فلا. إن نسيتها سترى `Target [XRepositoryInterface] is not instantiable`.

### المستودعات الموجودة

| المستودع | الموديول |
| --- | --- |
| `ReportRepository` | Reports |
| `EmployeeRepository` | Employees |
| `OrganizationRepository` | Organization |
| `CommunicationRepository` | Communications |
| `NotificationRepository` | Notifications |
| `PermissionRepository` | Permissions |

`Drive`, `Tasks`, `Forms`, `Hr` لا تستخدم المستودعات — منطقها في الخدمات أو المتحكمات.

### المعاملات

كل عملية تلمس أكثر من جدول تُغلَّف بـ`DB::transaction`:

```php
DB::transaction(function () use ($report, $user, $action, $note, $target, $role) {
    $report->update([...]);
    ReportAction::create([...]);
    Cache::put(...);
    $recipients?->get()->each(fn ($r) => CndNotification::create([...]));
});
```

---

## حقن الاعتماديات

### الخصائص المُروَّجة في المُنشئ

النمط السائد في PHP 8:

```php
public function __construct(
    private HrAccessService $access,
    private HrDocumentService $documents,
) {}
```

Laravel يحلّ الأصناف الملموسة تلقائياً. الواجهات تحتاج ربطاً في مزوّد.

### الحقن في الدوال

```php
public function index(Request $request): AnonymousResourceCollection
```

`Request` و`FormRequest` والموديلات المربوطة بالمسار تُحقن مباشرةً في دوال المتحكم.

---

## قائمة تحقق عند كتابة متحكم جديد

- [ ] كل دالة أقل من 15 سطراً
- [ ] لا `if` تفحص دوراً أو حالة — نقلها إلى خدمة
- [ ] التحقق في `FormRequest` لا في المتحكم
- [ ] الإرجاع عبر `Resource` لا `->toArray()`
- [ ] الاعتماديات محقونة في المُنشئ لا `app()` أو `new`
- [ ] الصلاحية مفروضة في `Routes/api.php`
- [ ] النطاق مفروض في خدمة، لا في المتحكم
- [ ] العمليات متعددة الجداول داخل `DB::transaction`
- [ ] إن أضفت مستودعاً: سجّلت المزوّد في `bootstrap/providers.php`
