# إدارة الحالة — React Query

## طبقات الحالة الأربع

| النوع | الأداة | مثال |
| --- | --- | --- |
| بيانات الخادم | React Query | التقارير، المهام، الإشعارات |
| حالة مشتركة عالمية | Context | اللغة، نوافذ التأكيد |
| حالة الشاشة الحالية | `useState` في `AppShell` | الدرج المفتوح، التنبيه |
| حالة مكوّن واحد | `useState` محلي | حقل بحث، نافذة مفتوحة |

القاعدة: **ما يأتي من الخادم لا يُخزَّن في `useState`** — يُترك لـReact Query.

---

## الإعداد — `src/Apihooks/queryClient.js`

```js
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60,
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});
```

| الإعداد | القيمة | الأثر |
| --- | --- | --- |
| `staleTime` | دقيقة | خلالها لا يُعاد الجلب عند إعادة تركيب المكوّن |
| `retry` | 1 | محاولة إعادة واحدة — لا يخفي الأعطال الحقيقية طويلاً |
| `refetchOnWindowFocus` | `false` | لا إعادة جلب عند العودة للنافذة |

> `refetchOnWindowFocus: false` اختيار متعمّد: النظام إداري داخلي، البيانات لا تتغير
> كل ثانية، وإعادة الجلب المستمرة حمل بلا فائدة.

الـ`queryClient` **يُصدَّر ككائن مفرد** ليُستخدم خارج React أيضاً — كما في
`src/App.jsx`:

```js
queryClient.clear();   // عند الدخول والخروج
```

---

## مفاتيح الاستعلام

المفاتيح المستخدمة فعلياً:

| المفتاح | الملف | البيانات |
| --- | --- | --- |
| `["dashboard", "stats"]` | `useDashboard.js` | إحصاءات اللوحة |
| `["dashboard", "reports"]` | `useDashboard.js` | آخر التقارير |
| `["dashboard", "notifications"]` | `useDashboard.js` | آخر الإشعارات |
| `["messages", "unread-count"]` | `useMessages.js` | عدد غير المقروء |
| `["forms"]` | `useForms.js` | الفورمات |

### قاعدة التسلسل الهرمي

```js
queryClient.invalidateQueries({ queryKey: ["dashboard"] });
```

يُبطل **كل** ما يبدأ بـ`["dashboard"]` — الثلاثة معاً. لذلك ابدأ المفتاح دائماً
بالنطاق العام ثم خصّص:

```
["dashboard"]                  ← يبطل كل شيء تحته
["dashboard", "reports"]       ← يبطل التقارير فقط
["reports", reportId]          ← تقرير واحد
```

---

## أنماط الـHooks

### استعلام مشروط بالصلاحية — `src/Apihooks/useMessages.js`

```js
export function useUnreadMessageCount(permissions = []) {
  const enabled =
    permissions.includes("messages.view") ||
    permissions.includes("correspondences.view");

  return useQuery({
    queryKey: ["messages", "unread-count"],
    queryFn: api.messageUnreadCount,
    enabled,
  });
}
```

`enabled: false` يمنع الطلب أصلاً. بدونه يرسل كل مستخدم طلباً يعود بـ403.

### طفرة مع إبطال — `src/Apihooks/useNotifications.js`

```js
export function useNotificationActions() {
  const queryClient = useQueryClient();

  const readAll = useMutation({
    mutationFn: api.readAllNotifications,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["dashboard", "notifications"] });
    },
  });

  return { notification, readAll };
}
```

نمط الطفرة: `mutationFn` تنفّذ، `onSuccess` تُبطل ما تأثر.

### إبطال متعدد — `src/Apihooks/useForms.js`

```js
export function useSubmitFormPublication() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ publicationId, values }) => api.submitForm(publicationId, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["forms"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}
```

تعبئة فورم تغيّر شيئين: قائمة الفورمات، وعدّاد "الفورمات غير المعبأة" في اللوحة.
كلاهما يُبطَل.

### الاستخدام في مكوّن

```jsx
const submitFormPublication = useSubmitFormPublication();

await submitFormPublication.mutateAsync({ publicationId, values });
// أو
submitFormPublication.mutate({ publicationId, values });
```

| الدالة | السلوك |
| --- | --- |
| `mutate()` | لا ترجع وعداً — استخدم `onSuccess`/`onError` |
| `mutateAsync()` | ترجع وعداً — تُستخدم مع `await` و`try/catch` |

---

## `src/Apihooks/useDashboard.js` — نمط مختلط

```js
export function useDashboardData(permissions = [], notify = () => {}) {
  const queryClient = useQueryClient();
  const [data, setData] = useState(null);
  const [reports, setReports] = useState([]);
  const [notifications, setNotifications] = useState([]);

  const dashboardQuery = useQuery({ queryKey: ["dashboard", "stats"], queryFn: api.dashboard });
  const reportsQuery = useQuery({
    queryKey: ["dashboard", "reports"], queryFn: api.reports,
    enabled: permissions.includes("reports.view"),
  });
  const notificationsQuery = useQuery({
    queryKey: ["dashboard", "notifications"], queryFn: api.notifications,
    enabled: permissions.includes("notifications.view"),
  });

  useEffect(() => { if (dashboardQuery.data) setData(dashboardQuery.data); }, [dashboardQuery.data]);
  // ... مثلها للاثنين الآخرين

  useEffect(() => {
    const error = dashboardQuery.error || reportsQuery.error || notificationsQuery.error;
    if (error) notify(error.message, "error");
  }, [dashboardQuery.error, reportsQuery.error, notificationsQuery.error, notify]);

  const load = useCallback(() => {
    queryClient.invalidateQueries({ queryKey: ["dashboard"] });
  }, [queryClient]);

  return { data, reports, setReports, notifications, setNotifications, load };
}
```

### لماذا نسخ البيانات إلى `useState`؟

ليتمكن `AppShell` من **التعديل المتفائل** — تحديث الواجهة فوراً قبل رد الخادم:

```jsx
setNotifications((items) => items.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() })));
```

الإشعارات تظهر مقروءة فوراً، والطلب يجري في الخلفية.

> **ملاحظة صريحة:** الملف يحوي `// eslint-disable-next-line react-hooks/set-state-in-effect`
> ثلاث مرات. هذا نمط مقبول هنا لأجل التعديل المتفائل، لكن **لا تعمّمه**. في الحالات
> العادية استخدم `useQuery` مباشرةً بلا نسخ. النمط الأنظف هو `onMutate` في `useMutation`.

كذلك توحيد الأخطاء: أي خطأ من الاستعلامات الثلاثة يُعرض عبر `notify` مرة واحدة.

---

## مسح الكاش عند تبديل المستخدم

`src/App.jsx` — في ثلاثة مواضع:

```jsx
// فشل استرجاع الجلسة
.catch(() => { queryClient.clear(); token.clear(); })

// عند الدخول
logged={(nextUser) => { queryClient.clear(); setUser(nextUser); }}

// عند الخروج
exit={() => { queryClient.clear(); token.clear(); setUser(null); }}
```

> **هذا حرج أمنياً.** بدونه يرى المستخدم الثاني على نفس الجهاز بيانات الأول
> من الكاش قبل وصول بياناته. لا تحذف أياً من هذه الاستدعاءات.

---

## متى تُبطل ماذا

| الفعل | ما تُبطله |
| --- | --- |
| إنشاء/تعديل تقرير | `["dashboard"]` — الإحصاءات والقائمة معاً |
| نقل مهمة | `["tasks"]` وإن ظهرت في اللوحة فـ`["dashboard"]` |
| قراءة إشعار | `["dashboard", "notifications"]` |
| تعبئة فورم | `["forms"]` و`["dashboard"]` |
| رفع ملف | `["drive"]` |
| إرسال رسالة | `["messages"]` |

القاعدة: **أبطل كل ما قد يتغيّر رقمه أو محتواه**، حتى في صفحة أخرى.

---

## أخطاء شائعة

| الخطأ | الأثر | الصواب |
| --- | --- | --- |
| نسيان `invalidateQueries` بعد طفرة | الواجهة لا تتحدث | أبطل في `onSuccess` |
| مفتاح غير مصفوفة | إبطال جزئي لا يعمل | `["scope", "sub"]` |
| إبطال ضيق جداً | جزء من الواجهة يبقى قديماً | أبطل النطاق الأعلى |
| استعلام بلا `enabled` | 403 لمن لا يملك الصلاحية | اشترط الصلاحية |
| `useState` لبيانات الخادم | حالة مكررة وتزامن يدوي | استخدم `useQuery` |
| نسيان `queryClient.clear()` | تسرّب بيانات بين الجلسات | امسح عند كل تبديل مستخدم |
