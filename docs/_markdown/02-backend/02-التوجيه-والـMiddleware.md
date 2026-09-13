# التوجيه والـMiddleware

## ملفات المسارات

| الملف | المحتوى |
| --- | --- |
| `BackEnd/routes/api.php` | محمّل فقط — يستدعي مسارات كل الموديولات |
| `BackEnd/routes/channels.php` | تصريح قنوات البث الخاصة |
| `BackEnd/routes/web.php` | مسارات الويب — شبه فارغ، المشروع API خالص |
| `BackEnd/routes/console.php` | أوامر artisan مخصصة |
| `BackEnd/app/Modules/*/Routes/api.php` | ★ المسارات الفعلية، ملف لكل موديول |

### المحمّل — `routes/api.php`

```php
Broadcast::routes(['middleware' => ['api', 'cnd.auth']]);

foreach (glob(app_path('Modules/*/Routes/api.php')) as $routeFile) {
    require $routeFile;
}
```

كل مسارات الموديولات تحمل بادئة `/api` تلقائياً لأنها محمّلة داخل ملف مسارات الـAPI.
فمسار `Route::get('reports', ...)` يصبح `GET /api/reports`.

> **تنبيه:** الترتيب يتبع ترتيب `glob` الأبجدي للمجلدات. إن أنشأت مسارين متعارضين في
> موديولين مختلفين سيفوز الأول أبجدياً. تجنّب التعارض بأسماء موارد واضحة.

---

## تسجيل الـMiddleware — `BackEnd/bootstrap/app.php`

Laravel 12 لا يستخدم `app/Http/Kernel.php`؛ كل شيء في `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'cnd.auth'         => \App\Http\Middleware\AuthenticateApiToken::class,
        'role'             => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission'       => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
    ]);
    $middleware->api(prepend: [\App\Http\Middleware\SetLocale::class]);
})
```

| الاسم المختصر | الصنف | متى يعمل |
| --- | --- | --- |
| `cnd.auth` | `AuthenticateApiToken` | عند وضعه على المسار صراحةً |
| `permission:<الاسم>` | spatie | عند وضعه صراحةً — بعد `cnd.auth` |
| `role:<الاسم>` | spatie | عند وضعه صراحةً |
| — | `SetLocale` | **تلقائياً على كل طلب API** عبر `prepend` |

`prepend` يعني أن `SetLocale` أول ما يُنفَّذ، قبل المصادقة — فرسالة الخطأ 401 نفسها
تصل باللغة الصحيحة.

---

## ترتيب التنفيذ

```
الطلب الوارد
   │
   ▼
[1] SetLocale                    ← تلقائي على كل طلبات API
   │    يقرأ X-Language ويضبط App::setLocale()
   ▼
[2] cnd.auth                     ← إن كان المسار داخل المجموعة
   │    يتحقق من Bearer token ويضبط المستخدم
   │    الفشل ← 401
   ▼
[3] permission:<الاسم>            ← إن وُجد على المسار
   │    يفحص صلاحية المستخدم
   │    الفشل ← 403
   ▼
[4] Route Model Binding
   │    {report} ← Report::findOrFail()
   │    الفشل ← 404
   ▼
[5] FormRequest
   │    التحقق من المدخلات
   │    الفشل ← 422
   ▼
[6] Controller
```

---

## `SetLocale` — `app/Http/Middleware/SetLocale.php`

```php
$locale = $request->header('X-Language', config('app.locale'));
App::setLocale(in_array($locale, ['ar', 'en'], true) ? $locale : config('app.fallback_locale'));
```

- اللغات المدعومة مكتوبة صراحةً: `['ar', 'en']`.
- أي قيمة أخرى تسقط إلى `fallback_locale`.
- **لإضافة لغة ثالثة**: عدّل هذه المصفوفة، وأضف مجلد ترجمة في `BackEnd/lang/`،
  وأضف الترجمات في `FrontEnd/src/i18n/parts/`.

اللغة تؤثر على رسائل `__('messages.*')` المستخدمة في
`Modules/Reports/Repositories/Eloquent/ReportRepository.php` وغيرها.

---

## `AuthenticateApiToken` — `app/Http/Middleware/AuthenticateApiToken.php`

```php
$token = $request->bearerToken();
$user = $token
    ? User::where('api_token', hash('sha256', $token))->where('is_active', true)->first()
    : null;

if (! $user) {
    return response()->json(['message' => 'انتهت الجلسة أو لا تملك صلاحية الوصول.'], 401);
}

$request->setUserResolver(fn () => $user);
Auth::setUser($user);
```

| النقطة | التفصيل |
| --- | --- |
| التخزين | `users.api_token` يحوي `sha256` للتوكن، لا التوكن نفسه |
| الطول | العمود `varchar(64)` — طول ناتج sha256 بالست عشري |
| الفهرس | `unique` على العمود، فالبحث سريع |
| `is_active` | جزء من الاستعلام — تعطيل مستخدم يقطع جلسته فوراً |
| النتيجة | يضبط المستخدم في الطلب وفي `Auth`، فتعمل صلاحيات spatie |

---

## أنماط الحماية في المسارات

### 1. مجموعة كاملة تحت المصادقة

النمط السائد في كل الموديولات:

```php
Route::middleware('cnd.auth')->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->middleware('permission:reports.view');
    Route::post('reports', [ReportController::class, 'store'])->middleware('permission:reports.create');
});
```

### 2. مسار عام بلا مصادقة

في `Modules/Core/Routes/api.php`:

```php
Route::get('status', [SystemController::class, 'health']);
Route::get('system/health', [SystemController::class, 'health']);
Route::post('auth/login', [SystemController::class, 'login']);
```

> `/status` موجود كمرادف محايد لـ`/system/health`، لأن بعض حاجبات الإعلانات ومضادات
> الفيروسات تحجب المسارات التي تحوي `system/health` باعتبارها إشارات تتبّع، فيفشل
> الطلب في المتصفح قبل أن يغادر أصلاً (`ERR_BLOCKED_BY_CLIENT`). التعليق موثّق في الملف نفسه.

في `Modules/Drive/Routes/api.php` — الروابط العامة:

```php
Route::get('public-files/{token}', [DriveController::class, 'publicDownload']);
Route::get('public-folders/{token}', [DriveController::class, 'publicFolder']);
```

الحماية هنا بالتوكن العشوائي في الرابط نفسه، وهو قابل للإلغاء.

في `Modules/Locale/Routes/api.php`:

```php
Route::get('locale/{lang}', [LocaleController::class, 'setLocale']);
Route::get('active-languages', [LocaleController::class, 'active']);
```

### 3. مسار مصادَق بلا صلاحية

عندما يكون النطاق نفسه هو الحماية — كل الموظفين لهم الحق، لكن كلٌّ يرى ما يخصّه:

```php
Route::get('tasks', [TaskController::class, 'index']);           // Tasks
Route::get('drive-files', [DriveController::class, 'index']);    // Drive
Route::get('hr/my-requests', [HrRequestController::class, 'mine']); // Hr
Route::get('profile/details', [EmployeeController::class, 'profile']); // Employees
```

هنا الفلترة داخل المتحكم أو الخدمة عبر `OrganizationScopeService` أو ما يقابلها.

### 4. قيد على شكل المعامل

في `Modules/Database/Routes/api.php`:

```php
Route::get('database-backups/{fileName}/download', [...])
    ->where('fileName', '[A-Za-z0-9._-]+');
```

`where` يمنع محارف المسار (`/`, `..`) فيغلق ثغرة اجتياز المسارات
(path traversal) على مستوى التوجيه قبل وصول الطلب للمتحكم.

---

## معالج الاستثناءات — `bootstrap/app.php`

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (\Throwable $exception, Request $request) {
        if (! $request->expectsJson()
            || $exception instanceof HttpExceptionInterface
            || $exception instanceof HttpResponseException
            || $exception instanceof AuthenticationException
            || $exception instanceof ValidationException) {
            return null;   // اترك Laravel يتصرف
        }

        $errorId = (string) Str::uuid();
        Log::error('Unhandled API exception', ['error_id' => $errorId, 'exception' => $exception]);

        $response = ['message' => 'حدث خطأ غير متوقع.', 'error_id' => $errorId];
        if (config('app.debug')) { /* يضيف exception, error, file, line, trace */ }

        return response()->json($response, 500);
    });
})
```

الاستثناءات المعروفة (404، 422، 401) تمرّ بمعالجة Laravel الافتراضية.
غير المتوقع فقط يُحوّل إلى استجابة موحّدة مع `error_id` يربط الاستجابة بسطر السجل.
التفاصيل في `08-الأخطاء-والسجلات.md`.

---

## عرض كل المسارات

```powershell
cd BackEnd
php artisan route:list --path=api
php artisan route:list --path=api/reports    # تصفية
php artisan route:list --json                # للمعالجة الآلية
```

القائمة الكاملة الموثّقة في `docs/06-api/01-مرجع-الـAPI.md`.
