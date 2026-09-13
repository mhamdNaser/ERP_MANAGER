# الترجمة و RTL

اللغتان المدعومتان: **العربية (افتراضية، RTL)** و**الإنجليزية (LTR)**.
المرجع الأصلي: `FrontEnd/I18N-CONVENTION.md`.

---

## البنية

```
src/i18n/
├── translations.js          يدمج القاموس الأساسي مع الأجزاء ويُصدّر { ar, en }
├── tr.js                    مترجم للاستخدام خارج React
├── permissionDescriptions.js وصف عربي لكل صلاحية
└── parts/
    ├── api.js       ( 35 سطراً)  رسائل طبقة الشبكة
    ├── ui.js        (117 سطراً)  عناصر الواجهة العامة
    ├── hr.js        (176 سطراً)  الموارد البشرية
    ├── task.js      (294 سطراً)  المهام
    ├── drive.js     (262 سطراً)  الملفات
    ├── guide.js     (387 سطراً)  الجولة الإرشادية
    └── formal.js    (585 سطراً)  المراسلات الرسمية
```

`translations.js` يدمجها:

```js
import apiPart from './parts/api';
import drivePart from './parts/drive';
// ...
const base = { ar: { /* المفاتيح المشتركة */ }, en: { /* ... */ } };
```

> **لماذا التقسيم؟** ليتمكن أكثر من مبرمج من العمل على نطاقات مختلفة بلا تعارض
> في ملف واحد ضخم. **أضف مفاتيحك في ملف الجزء الخاص بنطاقك، لا في `translations.js`.**

---

## الاستخدام داخل React

```jsx
import { useLanguage } from "../../Provider/LanguageContext";

const { t, lang, toggle } = useLanguage();

<button>{t("save")}</button>
<h1>{t("greeting", { name: user.name })}</h1>
```

الاستبدال بـ`:name`:

```js
greeting: 'صباح الخير، :name'      // ar
greeting: 'Good morning, :name'    // en
```

التنفيذ في `src/Provider/LanguageContext.jsx`:

```js
t: (key, values = {}) => Object.entries(values).reduce(
    (text, [name, value]) => text.replace(`:${name}`, String(value)),
    translations[lang][key],
),
```

## الاستخدام خارج React — `src/i18n/tr.js`

```js
export function tr(key, values = {}) {
  const dict = translations[language.get()] || translations.ar;
  const text = dict[key] ?? translations.ar[key] ?? key;
  return Object.entries(values).reduce(
    (acc, [name, value]) => acc.replace(`:${name}`, String(value)), text);
}
```

للوحدات التي لا تستطيع استخدام الـhook — مثل `src/Api/http.js`:

```js
if (response.status === 413) throw new Error(tr('api_payloadTooLarge'));
```

| الفرق | `t()` | `tr()` |
| --- | --- | --- |
| المصدر | سياق React | `localStorage` عند كل نداء |
| الاستخدام | المكوّنات | طبقة الـAPI والوحدات العادية |
| عند غياب المفتاح | `undefined` | يسقط إلى العربية ثم إلى المفتاح نفسه |

`tr()` أكثر تسامحاً — سلوك مقصود لأن رسالة خطأ ناقصة أهون من انهيار في طبقة الشبكة.

---

## تسمية المفاتيح

### مفاتيح مشترَكة — أعد استخدامها ولا تكرّرها

```
save, cancel, delete, edit, close, search, loading,
refresh, submit, reset, results, yes, no
```

### مفاتيح جديدة — سمِّها ببادئة نطاقك

| النطاق | البادئة | مثال |
| --- | --- | --- |
| الملفات | `drive_` | `drive_uploadFiles` |
| المهام | `task_` | `task_moveToDone` |
| المراسلات | `formal_` | `formal_addStage` |
| الموارد البشرية | `hr_` | `hr_leaveBalance` |
| الجولة | `guide_` | `guide_intro` |
| اللوحة | `dash_` | `dash_pendingCount` |
| طبقة الـAPI | `api_` | `api_timeout` |

> عند الشك، أنشئ مفتاحاً جديداً ببادئتك. **التكرار أأمن من إعادة استخدام خاطئة** —
> نفس الكلمة العربية قد تُترجم بشكل مختلف حسب السياق.

---

## ما يُترجم وما لا يُترجم

| يُترجم | لا يُترجم |
| --- | --- |
| النصوص الظاهرة | قيم `data-guide` |
| `placeholder` | أصناف CSS |
| `title` و`aria-label` و`alt` | القيم المرسلة للـAPI (`status`, `type`) |
| رسائل التنبيه والتأكيد | سجلات المتصفح |
| عناوين الأعمدة والأزرار | مسارات الاستيراد |

> **مهم:** القيم المرسلة للخادم تبقى بالإنجليزية دائماً — `'department_review'`
> و`'approved'` قيم بيانات لا نصوص عرض. تُعرض بالعربية عبر مفتاح ترجمة مقابل:
> ```js
> departmentReview: 'مراجعة القسم',
> approved: 'معتمد',
> ```

---

## تبديل اللغة و RTL

### السلسلة

```
LanguageToggle.jsx
   └─► toggle() من useLanguage()
         └─► setLang('en')
               └─► useEffect ──► language.set('en')   (Api/storage.js)
                     ├─ localStorage.LANGUAGE = 'en'
                     ├─ document.documentElement.lang = 'en'
                     └─ document.documentElement.dir  = 'ltr'
```

### الضبط المبكر — `src/main.jsx`

```js
const initialLanguage = localStorage.getItem("LANGUAGE") || "ar";
document.documentElement.lang = initialLanguage;
document.documentElement.dir = initialLanguage === "ar" ? "rtl" : "ltr";
```

قبل `createRoot` — يمنع وميض الاتجاه الخاطئ عند أول رسم.

### الخادم يعرف اللغة أيضاً

`src/Api/http.js` يرسل `X-Language` مع كل طلب، و
`BackEnd/app/Http/Middleware/SetLocale.php` يقرؤها. فرسائل الخطأ من الخادم تصل بلغة
المستخدم.

---

## قواعد RTL في التنسيق

من `FrontEnd/STYLEGUIDE.md` — **قاعدة غير قابلة للتفاوض**:

| استخدم (منطقي) | لا تستخدم (فيزيائي) |
| --- | --- |
| `ms-*` / `me-*` | `ml-*` / `mr-*` |
| `ps-*` / `pe-*` | `pl-*` / `pr-*` |
| `start-*` / `end-*` | `left-*` / `right-*` |
| `text-start` / `text-end` | `text-left` / `text-right` |
| `border-s` / `border-e` | `border-l` / `border-r` |

الخصائص المنطقية تنقلب تلقائياً مع `dir="rtl"`. الفيزيائية لا تنقلب —
فتنكسر الواجهة عند تبديل اللغة.

### ما يبقى فيزيائياً

- الأيقونات الاتجاهية (سهم "التالي") قد تحتاج انعكاساً يدوياً.
- الأرقام والتواريخ لا تنعكس.
- المخططات والرسوم قد تحتاج معالجة خاصة.

---

## إضافة مفاتيح — الإجراء

**1.** حدّد ملف الجزء المناسب في `src/i18n/parts/`، أو أنشئ ملفاً جديداً:

```js
// Translations for the assets screens.
export default {
  ar: {
    asset_title: "العهد والأصول",
    asset_code: "رمز العهدة",
    asset_assignTo: "تسليم إلى",
  },
  en: {
    asset_title: "Assets",
    asset_code: "Asset code",
    asset_assignTo: "Assign to",
  },
};
```

**2.** إن كان الملف جديداً، استورده وادمجه في `src/i18n/translations.js`.

**3.** استخدمه: `t("asset_title")`.

### قواعد ملف الجزء

- **نفس المفاتيح في `ar` و`en` بالضبط.** مفتاح ناقص في `en` يظهر `undefined`.
- القيمة العربية = النص الأصلي حرفياً.
- القيمة الإنجليزية = ترجمة طبيعية لا حرفية.
- لا تعدّل ملف جزء نطاق آخر.

---

## أخطاء شائعة

| الخطأ | العَرَض | الحل |
| --- | --- | --- |
| مفتاح في `ar` وليس في `en` | `undefined` عند التبديل | تحقق من التطابق |
| نص مكتوب مباشرة في JSX | لا يُترجم | استبدله بـ`t()` |
| `ml-4` بدل `ms-4` | الواجهة تنكسر بالإنجليزية | استخدم الخصائص المنطقية |
| ترجمة قيمة تُرسل للـAPI | الخادم يرفض القيمة | القيم تبقى إنجليزية |
| `useLanguage()` خارج المزوّد | خطأ `LanguageProvider missing` | تأكد أن المكوّن داخل `<LanguageProvider>` |
| استخدام `t()` في `Api/*.js` | لا يوجد سياق React | استخدم `tr()` |
| ضبط `localStorage.LANGUAGE` يدوياً | الاتجاه لا يتغيّر | استخدم `language.set()` |
