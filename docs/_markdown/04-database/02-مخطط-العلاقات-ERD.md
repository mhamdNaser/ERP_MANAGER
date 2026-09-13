# مخطط العلاقات — ERD

الرموز: `1──*` واحد إلى متعدد، `1──1` واحد إلى واحد، `*──*` متعدد إلى متعدد.
كل صندوق يذكر الجدول وأهم أعمدته.

---

## 1. الهيكل التنظيمي والمستخدمون

```
┌──────────────────────┐
│ branches             │
│ id, name, code       │
│ description,is_active│
└──────────┬───────────┘
           │ 1
           │
           │ *
┌──────────▼───────────┐        ┌──────────────────────┐
│ departments          │        │ offices              │
│ id, branch_id, name  │        │ id, name, code       │
│ code, is_active      │        │ description,is_active│
└──────────┬───────────┘        └──────────┬───────────┘
           │ 1                             │ 1
           │                               │
           │ *                             │ *
      ┌────▼───────────────────────────────▼────┐
      │ users                                    │
      │ id, name, email, password                │
      │ role, job_title, employee_number         │
      │ employment_type                          │
      │ branch_id, department_id, office_id      │
      │ api_token (sha256), is_active            │
      │ digital_signature_path                   │
      └────┬──────────────┬──────────────┬───────┘
           │ 1            │ 1            │ 1
           │ 1            │ 1            │ 1
   ┌───────▼──────┐ ┌─────▼────────┐ ┌──▼─────────────────┐
   │user_addresses│ │user_family_  │ │user_personal_      │
   │country, city │ │  details     │ │  details           │
   │district      │ │marital_status│ │birth_date          │
   │street        │ │spouse_name   │ │national_id, gender │
   │building      │ │children_count│ │height_cm, weight_kg│
   │details       │ │emergency_*   │ │blood_type          │
   └──────────────┘ └──────────────┘ │shoe/trouser/shirt/ │
                                     │  jacket_size       │
                                     └────────────────────┘
```

> `users` يتبع **فرعاً وقسماً** أو **مكتباً**. المكتب بديل تنظيمي مستقل، لا يتبع فرعاً.

### الأدوار والصلاحيات (spatie)

```
┌──────────┐        ┌────────────────┐        ┌──────────────┐
│  roles   │*──────*│role_has_       │*──────*│ permissions  │
│ id, name │        │  permissions   │        │ id, name     │
│guard_name│        └────────────────┘        │ guard_name   │
└────┬─────┘                                  └──────┬───────┘
     │ *                                             │ *
     │                                               │
     │ *                                             │ *
┌────▼──────────────┐                     ┌──────────▼────────┐
│ model_has_roles   │                     │model_has_         │
│ role_id           │                     │  permissions      │
│ model_id → users  │                     │ permission_id     │
└───────────────────┘                     │ model_id → users  │
                                          └───────────────────┘
```

---

## 2. التقارير وخطط العمل

```
┌──────────┐   ┌──────────────┐   ┌───────┐
│ branches │   │ departments  │   │ users │
└────┬─────┘   └──────┬───────┘   └───┬───┘
     │ 1              │ 1             │ 1
     │                │               │ (employee_id)
     │ *              │ *             │ *
┌────▼────────────────▼───────────────▼────┐
│ reports                                   │
│ id, employee_id, branch_id, department_id │
│ type          daily_report|daily_plan|    │
│               weekly_report|weekly_plan   │
│ period_start, period_end                  │
│ title, summary, achievements              │
│ challenges, next_steps                    │
│ status        draft|department_review|     │
│               branch_review|general_review │
│               |returned|approved           │
│ current_reviewer_role, submitted_at        │
└────────────────────┬──────────────────────┘
                     │ 1
                     │ *
      ┌──────────────▼──────────────┐
      │ report_actions               │
      │ id, report_id, actor_id      │
      │ action     submit|forward_*| │
      │            return|approve    │
      │ from_status, to_status, note │
      └──────────────────────────────┘
```

> `report_actions` سجل غير قابل للتعديل لكل انتقال. **لا تحذف منه**؛ هو الأثر التدقيقي.

---

## 3. المهام والملفات

```
┌──────────────┐            ┌───────┐
│ departments  │            │ users │
└──────┬───────┘            └───┬───┘
       │ 1                      │ 1  (creator_id / assignee_id)
       │ *                      │ *
┌──────▼────────────────────────▼──────┐
│ tasks                                 │
│ id, department_id, creator_id         │
│ assignee_id (nullable)                │
│ title, description                    │
│ status   planned|in_progress|         │
│          completed|cancelled          │
│ priority low|medium|high|urgent       │
│ label, due_date, position             │
│ completed_at                          │
└───┬───────────────────────┬───────────┘
    │ 1                     │ 1
    │ *                     │ *
┌───▼──────────────┐  ┌─────▼───────────────┐
│ task_activities  │  │ drive_files         │
│ task_id          │  │ (عبر task_id)       │
│ department_id    │  └─────────────────────┘
│ actor_id, action │
│ task_title       │
│ summary, details │
└──────────────────┘

┌──────────────────────────────┐
│ drive_folders                 │
│ id, owner_id, parent_id ──┐   │
│ department_id             │   │ ذاتية المرجع
│ scope  personal|department│◄──┘ (شجرة مجلدات)
│        |task|organization │
│ name, path, description   │
│ public_token, public_path │
└────┬──────────────────┬───┘
     │ 1                │ *
     │ *                │ *
┌────▼──────────────┐ ┌─▼────────────────────┐
│ drive_files       │ │ drive_folder_shares  │
│ id, uploader_id   │ │ drive_folder_id      │
│ department_id     │ │ user_id, shared_by   │
│ task_id, folder_id│ └──────────────────────┘
│ scope, name, path │
│ mime_type, size   │        ┌──────────────────────┐
│ public_token      │*──────*│ drive_file_shares    │
│ public_path       │        │ drive_file_id        │
└───────────────────┘        │ user_id, shared_by   │
                             └──────────────────────┘

┌──────────────────────┐
│ role_drive_quotas    │   حصة تخزين لكل دور
│ role, quota_bytes    │
└──────────────────────┘
```

---

## 4. الفورمات المخصصة

```
┌───────┐
│ users │ (creator_id)
└───┬───┘
    │ 1
    │ *
┌───▼──────────────────────────┐
│ custom_forms                  │
│ id, creator_id, title         │
│ description, target_group     │
│ default_scope                 │
│ default_duration_days         │
│ is_active                     │
└───┬───────────────────┬───────┘
    │ 1                 │ 1
    │ *                 │ *
┌───▼────────────────┐ ┌▼──────────────────────────────┐
│ custom_form_fields │ │ custom_form_publications      │
│ custom_form_id     │ │ custom_form_id, issuer_id     │
│ field_key, label   │ │ scope  organization|branch|   │
│ input_type         │ │        department             │
│  text|email|       │ │ target_group                  │
│  password|date|    │ │ branch_id, department_id      │
│  select|textarea|  │ │ visible_from, visible_until   │
│  number|checkbox   │ │ status, message               │
│ options (JSON)     │ └───────────┬───────────────────┘
│ placeholder        │             │ 1
│ help_text          │             │ *
│ is_required        │ ┌───────────▼───────────────────┐
│ sort_order         │ │ custom_form_submissions       │
└────────────────────┘ │ custom_form_id                │
                       │ custom_form_publication_id    │
                       │ user_id, version              │
                       │ payload (JSON)                │
                       │ submitted_at                  │
                       └───────────────────────────────┘
```

> `payload` من نوع JSON — الإجابات مخزّنة كمفتاح/قيمة حسب `field_key`.
> `version` يرقّم إعادة التعبئة، فلا تضيع الإجابات السابقة.

---

## 5. الرسائل والتعاميم والإشعارات

```
┌───────┐
│ users │
└─┬───┬─┘
  │   │ (sender_id / recipient_id)
  │ 1 │ 1
  │ * │ *
┌─▼───▼──────────────────────────┐
│ messages                        │
│ id, sender_id, recipient_id     │
│ subject, content, purpose       │
│ allow_reply                     │
│ read_at, last_reply_at          │
│ last_reply_sender_id            │
│ sender_read_at,recipient_read_at│
│ attachment_path/name/mime/size  │
└──────────────┬──────────────────┘
               │ 1
               │ *
   ┌───────────▼──────────────────┐
   │ message_replies              │
   │ message_id, sender_id        │
   │ content                      │
   │ attachment_path/name/mime/size│
   └──────────────────────────────┘

┌─────────────────────────┐        ┌──────────────────────┐
│ circulars                │*──────*│ circular_recipients  │
│ id, issuer_id, title     │        │ circular_id, user_id │
│ content, audience        │        │ read_at              │
│ attachment_*             │        └──────────────────────┘
└─────────────────────────┘

┌──────────────────────────────────────┐
│ cnd_notifications                     │
│ id, user_id, title, message           │
│ report_id      ──► reports            │
│ circular_id    ──► circulars          │
│ custom_form_id ──► custom_forms       │
│ custom_form_publication_id            │
│ read_at                               │
└──────────────────────────────────────┘
```

> `cnd_notifications` يربط بأربعة مصادر اختيارية. المصدر الموجود يحدّد ما يُفتح
> عند النقر — انظر `openNotification()` في `AppShell.jsx`.
> الجدول كان اسمه `ncm_notifications` قبل ترحيل إعادة التسمية.

---

## 6. المراسلات الرسمية

```
┌──────────────────────┐
│ external_entities     │
│ id, name, code        │
│ contact_name/email/   │
│   phone, address      │
└────┬──────────────────┘
     │ 1  (sender / recipient)
     │ *
┌────▼────────────────────────────────────┐
│ formal_correspondences                   │
│ id, parent_id ──┐  (سلسلة مراسلات)       │
│ reference_code  │◄─┘                     │
│ direction  external_to_internal |        │
│            internal_to_external |        │
│            internal_to_internal          │
│ status, subject, summary, creator_id     │
│ source_type/id/label                     │
│ target_type/id/label                     │
│ sender_external_entity_id                │
│ recipient_external_entity_id             │
│ recipient_branch_id                      │
│ recipient_department_id                  │
│ first_place, body                        │
│ attachment_path/name, word_path,pdf_path │
│ qr_payload, issued_at                    │
└──────────────┬───────────────────────────┘
               │ 1
               │ *
┌──────────────▼─────────────────────────┐
│ formal_correspondence_events            │
│ id, formal_correspondence_id, actor_id  │
│ assigned_user_id, assigned_by_id        │
│ assigned_at, task_id ──► tasks          │
│ source_type/id/label                    │
│ target_type/id/label                    │
│ action_required, decision_type          │
│ decision_status, responded_at           │
│ registry_number, letter_title           │
│ letter_body, book_number, qr_payload    │
│ word_path, pdf_path                     │
│ event, from_status, to_status           │
│ note, meta (JSON)                       │
└────┬─────────────────────┬──────────────┘
     │ 1                   │ 1
     │ *                   │ *
┌────▼──────────────────┐ ┌▼───────────────────────────┐
│ formal_correspondence_│ │ formal_correspondence_     │
│   documents           │ │   stage_responses          │
│ formal_correspondence_│ │ formal_correspondence_     │
│   id / event_id       │ │   event_id, actor_id       │
│ document_type         │ │ response_type              │
│  uploaded|            │ │ body, recommendation       │
│  internal_letter      │ │ attachment_*               │
│ title, source_label   │ └────────────────────────────┘
│ target_label, body    │
│ registry_number       │
│ book_number,qr_payload│
│ attachment_*          │
└───────────────────────┘
```

> `parent_id` ذاتي المرجع — يربط المراسلات المتسلسلة (رد على رد).
> `task_id` في الحدث يربط محطة معالجة بمهمة فعلية في لوحة المهام.

---

## 7. الموارد البشرية

```
┌───────┐
│ users │
└─┬───┬─┘
  │   │ (user_id / created_by_id)
  │ 1 │ 1
  │ * │ *
┌─▼───▼───────────────────────────┐
│ hr_requests                      │
│ id, reference_code (unique)      │
│ user_id, created_by_id           │
│ type   leave|departure|          │
│        mission|document          │
│ subtype annual|sick|unpaid       │
│         employment|salary        │
│ start_date, end_date             │
│ start_time, end_time             │
│ days, hours                      │
│ destination, reason              │
│ status pending_head|pending_hr|  │
│        pending_gm|approved|      │
│        rejected|cancelled        │
│ stage  hr|gm|done                │
│ attachment_path/name             │
│ decided_at, document_number      │
│ qr_payload, word_path, pdf_path  │
└──────────────┬───────────────────┘
               │ 1
               │ *
    ┌──────────▼─────────────────┐
    │ hr_request_actions          │
    │ hr_request_id, actor_id     │
    │ stage                       │
    │ action approve|reject|      │
    │        submit|cancel        │
    │ note                        │
    └─────────────────────────────┘

┌──────────────────────────────────┐
│ hr_leave_balances                 │
│ id, user_id, year                 │
│ annual_entitlement (افتراضي 30)   │
│ used_days                         │
│ UNIQUE(user_id, year)             │
│ [محسوب] remaining_days            │
└──────────────────────────────────┘
```

> `remaining_days` سمة محسوبة في `app/Models/HrLeaveBalance.php`، ليست عموداً.
> الخصم يتم عند **الاعتماد النهائي فقط** وللإجازة السنوية حصراً.

---

## 8. جداول النظام

| الجدول | الغرض |
| --- | --- |
| `migrations` | سجل الترحيلات المنفّذة |
| `cache` / `cache_locks` | مخزن الكاش (`CACHE_STORE=database`) — يُستخدم لسبب إعادة التقرير |
| `jobs` / `job_batches` / `failed_jobs` | طوابير المهام |
| `sessions` | جلسات الويب (الـAPI يستخدم التوكن) |
| `password_reset_tokens` | إعادة تعيين كلمة المرور |

---

## قواعد الحذف

| النمط | الأثر | أمثلة |
| --- | --- | --- |
| `cascadeOnDelete` | حذف الأب يحذف الأبناء | `departments.branch_id`, `reports.employee_id`, `tasks.department_id`, كل جداول المشاركة |
| `nullOnDelete` | حذف الأب يفرّغ الحقل | `users.branch_id`, `tasks.assignee_id`, `drive_files.department_id` |

> **انتبه:** حذف موظف يحذف تقاريره (`cascadeOnDelete`) لكن يترك المهام المسندة إليه
> بحقل فارغ (`nullOnDelete`). هذا مقصود: التقرير ملك صاحبه، والمهمة ملك القسم.
