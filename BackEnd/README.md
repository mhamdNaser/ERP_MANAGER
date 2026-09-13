# CND Manager BackEnd

تطبيق Laravel 12 يقدّم REST API لنظام CND Manager. الباك إند مقسم إلى Modules، ويعتمد PostgreSQL في البيئة الحالية، و`spatie/laravel-permission` لإدارة الأدوار والصلاحيات.

## التشغيل

```bash
composer install
php artisan migrate
php artisan db:seed
php artisan serve --host=127.0.0.1 --port=8000
```

للرسائل الفورية:

```bash
php artisan reverb:start --host=127.0.0.1 --port=8080
```

## الوحدات

- `Core`: الصحة، الدخول، المستخدم الحالي، الداشبورد، واللغة.
- `Reports`: التقارير وخطط العمل ومسار اعتمادها حسب الدور.
- `Communications`: الرسائل الداخلية، التعاميم، المكاتب، والمراسلات الرسمية.
- `Drive`: الملفات والمجلدات والمشاركة والروابط المباشرة والسعات التخزينية.
- `Forms`: الفورمات المخصصة والنشر والتعبئة.
- `Tasks`: لوحة المهام وسجل النشاط.
- `Organization` و`Employees`: الفروع والأقسام والموظفون والملفات الشخصية.
- `Permissions`: مصفوفة الصلاحيات.
- `Database`: النسخ الاحتياطية.

## الرسائل والمراسلات

الرسائل الداخلية القديمة تم تحويلها إلى:

- `messages`
- `message_replies`

وتدعم:

- مرفق للرسالة.
- مرفق للرد.
- إشارات غير مقروءة.
- بث فوري عبر Reverb.
- حذف الرسالة من المصدر للمرسل.

المراسلات الرسمية الجديدة تستخدم:

- `external_entities`
- `formal_correspondences`
- `formal_correspondence_events`
- `formal_correspondence_documents`

كل مراسلة رسمية هي سجل بعنوان ومرجع واتجاه، وكل سجل يحتوي خطاً زمنياً. كل محطة في الخط الزمني يمكن أن تحمل عدة وثائق، ولكل وثيقة مصدر وهدف. الوثيقة قد تكون ملفاً مرفوعاً أو كتاباً صادراً عن مؤسستنا من قالب Word رسمي قابل للتحويل إلى PDF.

## قوالب Word الرسمية

قوالب Word القابلة للتعديل موجودة في:

```text
resources/templates/documents/
```

القالب الأساسي الحالي لكتاب المرحلة الداخلية:

```text
resources/templates/documents/formal-correspondences/stage-internal-letter.docx
```

ملف ربط القوالب بالأجزاء التي يمكن توليد وثائق لها:

```text
config/document_templates.php
```

إعدادات QR داخل نفس الملف:

- `QR_LOGO_PATH`: مسار شعار اختياري يوضع في وسط QR.
- `QR_BRAND_TEXT`: نص بديل يظهر في وسط QR عند غياب الشعار.

الحقول المقترحة داخل ملف Word تكون بصيغة `{{field_name}}`، مثل:

```text
{{registry_number}}
{{gregorian_date}}
{{hijri_date}}
{{recipient}}
{{subject}}
{{body}}
{{signer_name}}
{{signer_role}}
{{qr_code}}
{{signature}}
```

توليد الوثائق يعبئ الحقول، يدرج QR، ويدرج التوقيع الرقمي إن وجد. QR يبقى صورة عادية داخل مكانه في القالب، بينما التوقيع يدرج كصورة عائمة داخل مساحة التوقيع حتى لا يغير تدفق النص أو يخفي QR.

## مسار التقارير والخطط

ينطبق نفس المسار على التقارير وخطط العمل:

```text
draft/returned -> department_review -> branch_review -> general_review -> approved
                         |                  |                  |
                         +-> returned       +-> returned       +-> returned
```

- الموظف أو الفني ينشئ المسودة ويعيد إرسال التقرير المرتجع.
- رئيس القسم يراجع ما وصل إلى `department_review` ضمن قسمه، ويمكنه الإرسال إلى مدير الفرع أو المدير العام أو الإعادة للموظف مع سبب.
- مدير الفرع يراجع ما وصل إلى `branch_review` ضمن فرعه، ويمكنه الإرسال إلى المدير العام أو الإعادة للموظف.
- المدير العام يرى ما وصل إلى `general_review` وما تم اعتماده، ويعتمد نهائياً أو يعيد للموظف.
- مدير قواعد البيانات يرى كل التقارير والخطط ويملك صلاحيات تعديل وانتقال كاملة.
- عند الإعادة يتم حفظ سبب الإعادة مؤقتاً في الكاش بالمفتاح `reports:return-note:{id}`، ويُحذف عند إعادة إرسال نفس التقرير.
- بعد الاعتماد لا يبقى إجراء الإعادة متاحاً.

الفلاتر المدعومة في الواجهة مرتبطة بالصلاحيات: التاريخ للجميع، الموظف لرئيس القسم وما فوق، القسم لمدير الفرع وما فوق، والفرع للمدير العام ومدير قواعد البيانات.

## أهم المسارات

- `GET /api/reports`
- `POST /api/reports`
- `PATCH /api/reports/{report}`
- `POST /api/reports/{report}/transition`
- `GET /api/messages`
- `POST /api/messages`
- `POST /api/messages/{message}/replies`
- `GET /api/messages/unread-count`
- `PATCH /api/messages/{message}/read`
- `DELETE /api/messages/{message}`
- `GET /api/formal-correspondences`
- `POST /api/formal-correspondences`
- `GET /api/formal-correspondences-directory`
- `POST /api/formal-correspondences/{formalCorrespondence}/events`

## Seeders

- `RolePermissionSeeder`: الصلاحيات والأدوار، ويشمل صلاحيات `formal_correspondences.*`.
- `FormalCorrespondenceSeeder`: مؤسسات خارجية ومراسلات رسمية وسلاسل زمنية ووثائق تجريبية.
- `TaskSeeder`, `TaskActivitySeeder`, `DashboardTimelineSeeder`: بيانات تشغيلية للعرض.

بيانات المراسلات التجريبية التي يعيد `FormalCorrespondenceSeeder` بناءها:

- `CND-COR-DEMO-0001`: واردة للديوان، تحويل للمدير العام، كتاب Word داخلي، دراسة قسم، وتنفيذ فرع.
- `CND-COR-DEMO-0002`: قرار رد خارجي مع كتاب Word مولد من قالب المرحلة.
- `CND-COR-DEMO-0003`: قرار تريث مع مرفق PDF خارجي.

لتحديث صلاحيات وبيانات المراسلات الرسمية على قاعدة موجودة:

```bash
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=FormalCorrespondenceSeeder
```

## التحقق

```bash
php -l app/Modules/Communications/Controllers/FormalCorrespondenceController.php
php artisan route:list --path=formal-correspondences
php artisan route:list --path=messages
php artisan test
```
