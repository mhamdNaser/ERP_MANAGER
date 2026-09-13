# طبقة الـAPI

## ثلاث طبقات

```
الصفحة / الـhook
      │
      ▼
src/Api/api.js          ← تعريف كل نقطة نهاية كدالة
      │
      ▼
src/Api/axios-client.js ← إعادة تصدير فقط (اسم تاريخي)
      │
      ▼
src/Api/http.js         ← الترويسات، المهلة، الأخطاء، fetch/XHR
      │
      ├─ src/config/environment.js   العنوان والمهلة
      └─ src/Api/storage.js          التوكن واللغة
```

---

## `src/config/environment.js`

```js
function required(name, value) {
    const normalized = value?.trim();
    if (!normalized) throw new Error(`Missing required environment variable: ${name}`);
    return normalized;
}

export const environment = Object.freeze({
    apiBaseUrl: required('VITE_API_BASE_URL', import.meta.env.VITE_API_BASE_URL).replace(/\/+$/, ''),
    apiTimeoutMs: positiveNumber(import.meta.env.VITE_API_TIMEOUT_MS, 300000),
});
```

| السلوك | الفائدة |
| --- | --- |
| يرمي خطأً عند غياب العنوان | **فشل صريح عند الإقلاع** بدل 404 غامض لاحقاً |
| `.replace(/\/+$/, '')` | يزيل الشرطة الأخيرة فلا تنشأ `//` في المسارات |
| `Object.freeze` | لا يمكن تعديل الإعدادات وقت التشغيل |
| مهلة افتراضية 5 دقائق | كبيرة عمداً لأجل رفع وتنزيل الملفات الكبيرة |

---

## `src/Api/storage.js`

```js
export const language = {
    get: () => localStorage.getItem('LANGUAGE') || 'ar',
    set: (value) => {
        localStorage.setItem('LANGUAGE', value);
        document.documentElement.lang = value;
        document.documentElement.dir = value === 'ar' ? 'rtl' : 'ltr';
    },
};

export const token = {
    get:   () => localStorage.getItem('cnd_token'),
    set:   (value) => localStorage.setItem('cnd_token', value),
    clear: () => localStorage.removeItem('cnd_token'),
};
```

`language.set()` يفعل ثلاثة أشياء معاً — التخزين واللغة والاتجاه. لا تحدّث
`localStorage` مباشرةً في أي مكان آخر.

> مفتاح التوكن `cnd_token` (كان `ncm_token` قبل إعادة التسمية). لهذا احتاج المستخدمون
> تسجيل دخول واحداً بعد ترحيل `2026_06_14_000002_rename_ncm_brand_to_cnd.php`.

---

## `src/Api/http.js` — الطبقة الدنيا

### الترويسات

```js
function headers(options, json = true) {
    return {
        ...(json ? { 'Content-Type': 'application/json' } : {}),
        Accept: 'application/json',
        'X-Language': language.get(),
        ...(token.get() ? { Authorization: `Bearer ${token.get()}` } : {}),
        ...options.headers,
    };
}
```

> **`Content-Type` يُحذف مع `FormData`.** لو أُرسل يدوياً لن يضيف المتصفح الـ`boundary`
> وسيفشل الرفع. هذا سبب المعامل `json`.

### المهلة

```js
const controller = new AbortController();
const timeout = window.setTimeout(() => controller.abort(), environment.apiTimeoutMs);
try {
    return await fetch(`${environment.apiBaseUrl}${path}`, { ...options, signal: controller.signal });
} catch (error) {
    if (error.name === 'AbortError') throw new Error(tr('api_timeout'), { cause: error });
    throw error;
} finally {
    window.clearTimeout(timeout);
}
```

`AbortError` يُترجم إلى رسالة عربية مفهومة. `finally` يضمن تنظيف المؤقّت.

### الدوال الأربع

| الدالة | التقنية | ترجع | الاستخدام |
| --- | --- | --- | --- |
| `request()` | fetch | JSON | كل الطلبات العادية |
| `requestBlob()` | fetch | Blob | تنزيل بلا مؤشر تقدّم |
| `requestBlobProgress()` | XHR | Blob | تنزيل مع مؤشر تقدّم |
| `requestUpload()` | XHR | JSON | رفع مع مؤشر تقدّم |

> **لماذا XHR؟** `fetch` لا يوفّر أحداث تقدّم للرفع. الدالتان الأخيرتان تستخدمان
> `XMLHttpRequest` لأجل `xhr.upload.onprogress` و`xhr.onprogress`.

#### شكل حمولة التقدّم

```js
onProgress({
  phase: 'downloading',        // preparing | downloading
  loaded: event.loaded,
  total: event.lengthComputable ? event.total : 0,
  percent: event.lengthComputable ? Math.round((event.loaded / event.total) * 100) : 0,
  lengthComputable: event.lengthComputable,
});
```

`lengthComputable` يخبرك إن كان الحجم الكلي معروفاً — عند `false` اعرض مؤشراً غير محدد
بدل نسبة مئوية خاطئة.

### معالجة الأخطاء

```js
const data = await response.json().catch(() => ({}));
if (response.status === 413) throw new Error(tr('api_payloadTooLarge'));
if (!response.ok) throw new Error(data.message || fallbackMessage());
return data;
```

413 مُعالَجة خصيصاً لأن الخادم قد يرجع HTML لا JSON عند تجاوز حد PHP.

`readBlobError()` يقرأ رسالة الخطأ من داخل Blob — لأن الاستجابة الفاشلة في طلب تنزيل
تصل كـBlob لا كـJSON.

---

## `src/Api/api.js` — تعريف نقاط النهاية

286 سطراً مقسّمة إلى ثمانية كائنات ثم مدمجة:

```js
export const api = {
  ...systemApi,        // الصحة، الدخول، اللوحة، الترجمات
  ...taskApi,          // المهام والسجل
  ...driveApi,         // الملفات والمجلدات
  ...reportApi,        // التقارير والإشعارات
  ...formsApi,         // الفورمات
  ...communicationApi, // الرسائل والمراسلات والتعاميم
  ...organizationApi,  // الفروع والأقسام والموظفون والمكاتب و HR
  ...administrationApi,// الصلاحيات والنسخ الاحتياطي
};
```

### الأنماط

```js
// طلب بسيط
reports: () => request("/reports"),

// مع جسم JSON
createReport: (data) => request("/reports", { method: "POST", body: JSON.stringify(data) }),

// بمعامل في المسار
notification: (id) => request(`/notifications/${id}`),

// بمعاملات استعلام مُنظَّفة
taskActivities: (params = {}) => {
  const query = new URLSearchParams(
    Object.entries(params).filter(([, value]) => value !== "" && value != null),
  ).toString();
  return request(`/task-activities${query ? `?${query}` : ""}`);
},

// رفع مع تقدّم
uploadDriveFiles: (files, data, onProgress) =>
  requestUpload("/drive-files", { method: "POST", body: filesFormData(files, data), onProgress }),

// تنزيل
downloadDriveFile: (id) => requestBlob(`/drive-files/${id}/download`),

// اسم ملف في المسار — يُرمَّز
deleteDatabaseBackup: (fileName) =>
  request(`/database-backups/${encodeURIComponent(fileName)}`, { method: "DELETE" }),
```

> **تصفية المعاملات الفارغة مهمة** — بدونها تُرسل `?department_id=` فيراها الخادم
> سلسلة فارغة لا قيمة غائبة، وقد يفشل التحقق.

---

## `src/utils/formData.js`

```js
export function toFormData(data) {
  const formData = new FormData();
  Object.entries(data).forEach(([key, value]) => {
    if (value === null || value === undefined || value === "") return;   // تجاهل الفارغ
    if (Array.isArray(value)) {
      value.forEach((item) => {
        if (item === null || item === undefined || item === "") return;
        formData.append(`${key}[]`, item instanceof File ? item : String(item));
      });
      return;
    }
    formData.append(key, value instanceof File ? value : String(value));
  });
  return formData;
}

export function filesFormData(files, data) {
  const formData = toFormData(data);
  files.forEach((file) => formData.append("files[]", file));
  return formData;
}
```

| السلوك | السبب |
| --- | --- |
| تجاهل `null` و`""` | `FormData` يحوّلها إلى النص `"null"` — يفسد التحقق في الخادم |
| `key[]` للمصفوفات | الصيغة التي يفهمها Laravel |
| `String(value)` | `FormData` يقبل نصوصاً وملفات فقط |
| `files[]` ثابت | يطابق قاعدة `'files.*'` في `DriveController::store()` |

---

## إضافة نقطة نهاية جديدة

**1.** أضف الدالة إلى الكائن المناسب في `src/Api/api.js`:

```js
const organizationApi = {
  // ...
  assets: () => request("/assets"),
  createAsset: (data) => request("/assets", { method: "POST", body: JSON.stringify(data) }),
};
```

**2.** استخدمها في الصفحة أو الـhook:

```jsx
const { data, isLoading } = useQuery({ queryKey: ["assets"], queryFn: api.assets });
```

**3.** لرسائل خطأ جديدة أضف المفاتيح في `src/i18n/parts/api.js`.

### قواعد

| القاعدة | السبب |
| --- | --- |
| لا تستدعِ `fetch` مباشرةً في مكوّن | تفقد الترويسات والمهلة ومعالجة الأخطاء |
| لا تكتب مسار API في مكوّن | مركزيتها في `api.js` تجعل التعديل بمكان واحد |
| استخدم `encodeURIComponent` لأي معامل نصي | أسماء الملفات قد تحوي محارف خاصة |
| نظّف معاملات الاستعلام الفارغة | تمنع سلاسل فارغة تُفشل التحقق |
| للرفع الكبير استخدم `requestUpload` | يعطي المستخدم مؤشر تقدّم |
