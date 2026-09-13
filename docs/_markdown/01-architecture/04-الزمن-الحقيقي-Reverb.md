# البث اللحظي — Reverb و Echo

## ما الذي يُبثّ فعلاً؟

الرسائل الداخلية فقط. حدثان اثنان:

| الحدث | الملف | يُطلق عند |
| --- | --- | --- |
| `message.created` | `BackEnd/app/Events/MessageCreated.php` | إنشاء رسالة جديدة |
| `message.reply.created` | `BackEnd/app/Events/MessageReplyCreated.php` | إضافة رد على رسالة |

كل ما عداه (التقارير، المهام، الإشعارات) يعتمد على إعادة الجلب عبر React Query لا على البث.

---

## السلسلة الكاملة

```
 المستخدم (أ)                    الخادم                      المستخدم (ب)
      │                            │                              │
      │  POST /api/messages        │                              │
      ├───────────────────────────►│                              │
      │                            │ CommunicationController      │
      │                            │   ::storeMessage()           │
      │                            │        │                     │
      │                            │        ▼                     │
      │                            │ CommunicationRepository      │
      │                            │   ::createMessage()          │
      │                            │        ├─ Message::create()  │
      │                            │        └─ event(MessageCreated)
      │                            │              │               │
      │                            │              ▼               │
      │                            │      ShouldBroadcastNow      │
      │                            │      (بلا طابور)             │
      │                            │              │               │
      │  201 + JSON                │              ▼               │
      │◄───────────────────────────┤        ┌───────────┐         │
      │                            │        │  Reverb   │         │
      │                            │        │  :8080    │         │
      │                            │        └─────┬─────┘         │
      │                            │              │ private-user.{id}
      │                            │              └────────────────►│
      │                            │                              │ realtime.js
      │                            │                              │ AppShell.jsx
      │                            │                              │ يحدّث العدّاد
```

---

## جانب الخادم

### تعريف الحدث — `app/Events/MessageCreated.php`

```php
class MessageCreated implements ShouldBroadcastNow
{
    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->message->recipient_id}")];
    }

    public function broadcastAs(): string { return 'message.created'; }

    public function broadcastWith(): array { /* الحمولة المرسلة */ }
}
```

| العنصر | المعنى |
| --- | --- |
| `ShouldBroadcastNow` | يُبثّ في نفس الطلب. لو كان `ShouldBroadcast` لاحتاج عامل طوابير يعمل |
| `PrivateChannel("user.{id}")` | قناة خاصة لكل مستخدم — تتطلب تصريحاً |
| `broadcastAs()` | اسم الحدث على السلك. الفرونت يستمع بنقطة بادئة: `.message.created` |
| `broadcastWith()` | يحمّل العلاقات ويضيف `unread_for_user = true` قبل الإرسال |

> **النقطة البادئة مهمة.** بدون `broadcastAs()` يرسل Laravel اسم الصنف الكامل
> `App\Events\MessageCreated`. مع `broadcastAs()` يجب أن يستمع العميل إلى
> `".message.created"` بنقطة، وإلا لن يصله شيء.

### تصريح القناة — `BackEnd/routes/channels.php`

```php
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
```

هذا هو الحاجز الأمني: لا يستطيع مستخدم الاشتراك في قناة غيره.

### مسار التصريح — `BackEnd/routes/api.php`

```php
Broadcast::routes(['middleware' => ['api', 'cnd.auth']]);
```

ينشئ `POST /api/broadcasting/auth`. يمرّ عبر `cnd.auth` — أي أن العميل يجب أن يرسل
Bearer token عند الاشتراك، وهذا ما يفعله `realtime.js`.

### مقاومة الأعطال

عند توقف Reverb **لا يفشل إنشاء الرسالة**. الخادم يسجّل تحذيراً ويكمل الطلب.
النتيجة: الرسالة تُحفظ وتظهر عند إعادة الجلب، لكن بلا تحديث لحظي.
هذا سلوك مقصود — البث تحسين لا شرط.

---

## جانب العميل

### إنشاء العميل — `FrontEnd/src/realtime.js`

```js
export function createEcho() {
  if (import.meta.env.VITE_REVERB_ENABLED !== "true") return null;
  const key = import.meta.env.VITE_REVERB_APP_KEY;
  if (!key) return null;

  return new Echo({
    broadcaster: "reverb",
    key,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === "https",
    authEndpoint: `${import.meta.env.VITE_API_BASE_URL || "/api"}/broadcasting/auth`,
    auth: { headers: { Authorization: token.get() ? `Bearer ${token.get()}` : "" } },
  });
}
```

بوابتان للإيقاف: `VITE_REVERB_ENABLED !== "true"` أو غياب المفتاح ← يعيد `null`.
كل الكود المستهلك يتعامل مع `null` بأمان.

### الاشتراك — `FrontEnd/src/Layout/AppShell.jsx`

```js
const echo = createEcho();
if (!echo) return undefined;

const channel = echo.private(`user.${user.id}`);
channel.listen(".message.created", bump);
channel.listen(".message.reply.created", bump);

return () => {
  echo.leave(`user.${user.id}`);
  echo.disconnect();
};
```

الاشتراك في `AppShell` لا في صفحة الرسائل — ليصل التنبيه أينما كان المستخدم.
دالة التنظيف إلزامية وإلا تتراكم الاتصالات عند كل إعادة تصيير.

---

## المكتبات المستخدمة

| الطرف | الحزمة | الدور |
| --- | --- | --- |
| الخادم | `laravel/reverb` ^1.10 | خادم WebSocket بروتوكول متوافق مع Pusher |
| العميل | `laravel-echo` ^2.3.7 | تجريد الاشتراك في القنوات |
| العميل | `pusher-js` ^8.5.0 | تنفيذ بروتوكول Pusher — يوضع في `window.Pusher` |

Reverb يتكلم بروتوكول Pusher، لذلك يُستخدم `pusher-js` مع خادم محلي بلا اشتراك خارجي.

---

## التشغيل والتحقق

```powershell
cd BackEnd
php artisan reverb:start --host=127.0.0.1 --port=8080
```

للتشخيص المفصّل:

```powershell
php artisan reverb:start --debug
```

### قائمة تحقق عند عدم وصول البث

| الفحص | كيف |
| --- | --- |
| Reverb يعمل؟ | يجب أن ترى `Starting server on 0.0.0.0:8080` |
| المفتاحان متطابقان؟ | `REVERB_APP_KEY` = `VITE_REVERB_APP_KEY` |
| التفعيل في الفرونت؟ | `VITE_REVERB_ENABLED=true` نصاً حرفياً |
| الاتصال قائم؟ | تبويب Network في المتصفح ← فلتر WS ← يجب أن ترى اتصالاً مفتوحاً |
| التصريح نجح؟ | `POST /api/broadcasting/auth` يجب أن يعيد 200 لا 403 |
| اسم الحدث صحيح؟ | نقطة بادئة: `.message.created` وليس `message.created` |
| أعدت تشغيل Vite؟ | متغيرات البيئة تُقرأ عند الإقلاع فقط |

---

## إضافة حدث بثّ جديد

مثال: بثّ إشعار عند اعتماد تقرير.

**1.** أنشئ `BackEnd/app/Events/ReportApproved.php` على نمط `MessageCreated.php`.

**2.** أطلقه في `Modules/Reports/Repositories/Eloquent/ReportRepository.php` داخل
`transition()` بعد إنشاء الإشعار:

```php
event(new ReportApproved($report, $recipient->id));
```

**3.** إن كانت القناة جديدة صرّح بها في `BackEnd/routes/channels.php`.
لو استخدمت `user.{id}` القائمة فلا حاجة.

**4.** استمع في `FrontEnd/src/Layout/AppShell.jsx`:

```js
channel.listen(".report.approved", (payload) => {
  queryClient.invalidateQueries({ queryKey: ["reports"] });
});
```

> **قاعدة:** لا ترسل في `broadcastWith()` بيانات لا يحق للمستلم رؤيتها.
> القناة تضمن الوصول للشخص الصحيح، لكن محتوى الحمولة مسؤوليتك أنت.
