# نمط Modules — كيف يعمل وكيف تضيف موديولاً

## لماذا Modules أصلاً؟

Laravel الافتراضي يجمع كل المتحكمات في `app/Http/Controllers` وكل المسارات في
`routes/api.php`. مع 13 نطاق عمل يصبح هذا غير قابل للصيانة. النمط المستخدم هنا يضع
**كل ما يخص نطاقاً واحداً في مجلد واحد**، فتفتح مجلد `Reports` وتجد كل شيء عنه.

الفائدة العملية: عند طلب "عدّل شيئاً في التقارير" تعرف المجلد فوراً بلا بحث.

---

## آلية التحميل التلقائي

`BackEnd/routes/api.php` بأكمله:

```php
Broadcast::routes(['middleware' => ['api', 'cnd.auth']]);

foreach (glob(app_path('Modules/*/Routes/api.php')) as $routeFile) {
    require $routeFile;
}
```

النتيجة: **مجرّد إنشاء `app/Modules/<الاسم>/Routes/api.php` يجعل مساراته حيّة.**
لا تسجيل يدوي ولا تعديل على ملف مركزي.

أما الـService Providers فليست تلقائية — تُسجَّل في `BackEnd/bootstrap/providers.php`.

---

## الهيكل الكامل لموديول

```
app/Modules/<الاسم>/
├── Routes/
│   └── api.php                 ★ إلزامي — نقطة الدخول
├── Controllers/
│   └── <الاسم>Controller.php
├── Requests/
│   ├── Store<X>Request.php
│   └── Update<X>Request.php
├── Resources/
│   └── <X>Resource.php
├── Services/
│   └── <X>Service.php
├── Repositories/
│   ├── Interfaces/
│   │   └── <X>RepositoryInterface.php
│   └── Eloquent/
│       └── <X>Repository.php
└── Providers/
    └── <الاسم>ServiceProvider.php
```

**الموديولات ليست ملزمة بكل الأجزاء.** انظر ما هو موجود فعلاً:

| الموديول | Routes | Controllers | Requests | Resources | Services | Repositories | Providers |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| Core | ✔ | ✔ | ✔ | — | ✔ | — | — |
| Organization | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Offices | ✔ | ✔ | ✔ | — | — | — | — |
| Employees | ✔ | ✔ | ✔ | ✔ | — | ✔ | ✔ |
| Reports | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Tasks | ✔ | ✔ | — | ✔ | ✔ | — | ✔ |
| Drive | ✔ | ✔ | — | ✔ | — | — | — |
| Forms | ✔ | ✔ | — | ✔ | ✔ | — | — |
| Communications | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Notifications | ✔ | ✔ | — | ✔ | — | ✔ | ✔ |
| Hr | ✔ | ✔ | ✔ | — | ✔ | — | — |
| Permissions | ✔ | ✔ | ✔ | — | — | ✔ | ✔ |
| Database | ✔ | ✔ | — | — | ✔ | — | — |
| Locale | ✔ | ✔ | — | — | — | — | ✔ |

**متى تحتاج Repository؟** عندما تريد استبدال مصدر البيانات أو اختبار المنطق بمحاكاة.
`Drive` لا يستخدمه لأن منطقه مرتبط بنظام الملفات وليس قابلاً للاستبدال عملياً.

---

## نمط Repository + Interface

المثال الأوضح — `Reports`:

**العقد** — `Modules/Reports/Repositories/Interfaces/ReportRepositoryInterface.php`

```php
interface ReportRepositoryInterface
{
    public function visibleTo(User $user, ?int $limit = null): Collection;
    public function statistics(User $user): array;
    public function createFor(User $user, array $data): Report;
    public function updateReturned(User $user, Report $report, array $data): Report;
    public function transition(User $user, Report $report, string $action, ?string $note): Report;
}
```

**التنفيذ** — `Modules/Reports/Repositories/Eloquent/ReportRepository.php`

**الربط** — `Modules/Reports/Providers/ReportsServiceProvider.php`

```php
$this->app->bind(ReportRepositoryInterface::class, ReportRepository::class);
```

**الاستهلاك** — `Modules/Reports/Controllers/ReportController.php`

```php
public function __construct(private ReportRepositoryInterface $reports) {}
```

الحاوية تحقن التنفيذ تلقائياً. المتحكم لا يعرف Eloquent إطلاقاً.

---

## إضافة موديول جديد — الخطوات الكاملة

مثال عملي: موديول **Assets** لإدارة العهد والأصول.

### الخطوة 1 — الترحيل

`BackEnd/database/migrations/2026_08_10_000001_create_assets_table.php`

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('status')->default('in_service')->index();
            $table->date('assigned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('assets'); }
};
```

### الخطوة 2 — الموديل

`BackEnd/app/Models/Asset.php` — الموديلات تبقى مركزية في `app/Models`، لا داخل الموديول.

```php
class Asset extends Model
{
    protected $fillable = ['holder_id', 'department_id', 'code', 'name', 'status', 'assigned_at'];
    protected function casts(): array { return ['assigned_at' => 'date']; }

    public function holder(): BelongsTo { return $this->belongsTo(User::class, 'holder_id'); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
}
```

### الخطوة 3 — الصلاحيات

أضف الأسماء إلى مصفوفة `$permissions` في
`BackEnd/database/seeders/RolePermissionSeeder.php`، ثم اربطها بالأدوار في `$roles`:

```php
'assets.view', 'assets.manage',
```

ولترحيل بيئة موجودة أنشئ ترحيل منح على غرار
`2026_08_02_000001_grant_hr_view_to_general_manager.php`.

### الخطوة 4 — التحقق

`app/Modules/Assets/Requests/StoreAssetRequest.php`

```php
class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool { return true; }   // الصلاحية مفروضة في المسار

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:assets,code'],
            'name' => ['required', 'string', 'max:255'],
            'holder_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', 'in:in_service,maintenance,retired'],
        ];
    }
}
```

### الخطوة 5 — النطاق

`app/Modules/Assets/Services/AssetScopeService.php` — اتبع نمط `OrganizationScopeService`:

```php
public function visibleTo(User $actor): Builder
{
    return match ($actor->primaryRole()) {
        'general_manager', 'database_manager' => Asset::query(),
        'branch_manager'  => Asset::whereHas('department', fn ($q) => $q->where('branch_id', $actor->branch_id)),
        'department_head' => Asset::where('department_id', $actor->department_id),
        default           => Asset::where('holder_id', $actor->id),
    };
}
```

### الخطوة 6 — المخرجات

`app/Modules/Assets/Resources/AssetResource.php`

```php
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'code' => $this->code,
        'name' => $this->name,
        'status' => $this->status,
        'assigned_at' => $this->assigned_at?->toDateString(),
        'holder' => $this->whenLoaded('holder', fn () => [
            'id' => $this->holder->id,
            'name' => $this->holder->name,
        ]),
    ];
}
```

### الخطوة 7 — المتحكم

`app/Modules/Assets/Controllers/AssetController.php`

```php
class AssetController extends Controller
{
    public function __construct(private AssetScopeService $scope) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return AssetResource::collection(
            $this->scope->visibleTo($request->user())->with('holder')->latest()->get()
        );
    }

    public function store(StoreAssetRequest $request): AssetResource
    {
        return new AssetResource(Asset::create($request->validated()));
    }
}
```

### الخطوة 8 — المسارات

`app/Modules/Assets/Routes/api.php`

```php
Route::middleware('cnd.auth')->group(function () {
    Route::get('assets', [AssetController::class, 'index'])->middleware('permission:assets.view');
    Route::post('assets', [AssetController::class, 'store'])->middleware('permission:assets.manage');
});
```

**لا حاجة لتسجيل أي شيء** — `glob` في `routes/api.php` يلتقطه.

### الخطوة 9 — الفرونت أند

| الملف | ما تضيفه |
| --- | --- |
| `FrontEnd/src/Api/api.js` | `assets: () => request("/assets")` وأخواتها |
| `FrontEnd/src/Page/Assets/index.jsx` | الصفحة |
| `FrontEnd/src/Layout/AppShell.jsx` | استيراد الصفحة وربطها بالتبويب |
| `FrontEnd/src/Apihooks/route.jsx` | إضافة `"assets"` إلى `tabRouteIds` |
| `FrontEnd/json/navigation.json` | عنصر القائمة مع `permission: "assets.view"` |
| `FrontEnd/src/i18n/parts/ui.js` | مفاتيح الترجمة `ar` و`en` |

### الخطوة 10 — الاختبار

`BackEnd/tests/Feature/AssetScopeTest.php` — انظر `02-backend/09-الاختبارات.md`.

---

## قائمة تحقق قبل اعتبار الموديول مكتملاً

- [ ] الترحيل يعمل صعوداً ونزولاً (`migrate` ثم `migrate:rollback`)
- [ ] الصلاحيات مضافة في `RolePermissionSeeder` **وفي ترحيل منح** للبيئات القائمة
- [ ] كل مسار محمي بـ`cnd.auth` وبصلاحية مناسبة
- [ ] النطاق مطبَّق في الخادم لا في الواجهة فقط
- [ ] الـResource لا يسرّب أعمدة حساسة
- [ ] مفاتيح الترجمة موجودة بالعربية والإنجليزية
- [ ] لا ملف يتجاوز 400 سطر
- [ ] الوثائق: أضف ملفاً في `docs/05-modules/` وحدّث `docs/06-api/01-مرجع-الـAPI.md`
