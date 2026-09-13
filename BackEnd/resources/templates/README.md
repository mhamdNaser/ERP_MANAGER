# Document Templates

هذا المجلد مخصص لقوالب Word الرسمية القابلة للتعديل.

المسار الأساسي للتعديل:

```text
BackEnd/resources/templates/documents/
```

القوالب المقترحة داخل المشروع:

- `documents/formal-correspondences/stage-internal-letter.docx`
  كتاب مرحلة داخلية صادر عن مؤسستنا. هذا هو القالب الأهم حالياً، وقد تم وضع نسخة أولية من ملف Word الذي زودتنا به.
- `documents/formal-correspondences/stage-external-reply.docx`
  كتاب رد صادر لجهة خارجية عند قرار المدير العام بالرد.
- `documents/formal-correspondences/stage-study-report.docx`
  نموذج دراسة يملؤه فرع أو قسم أو مكتب.
- `documents/formal-correspondences/stage-execution-report.docx`
  نموذج تنفيذ يملؤه فرع أو قسم أو مكتب.
- `documents/messages/message-export.docx`
  تصدير رسالة داخلية أو سلسلة ردود ككتاب/محضر.
- `documents/reports/approved-report.docx`
  إخراج تقرير معتمد بصيغة رسمية.
- `documents/custom-forms/submission-export.docx`
  إخراج تعبئة فورم مخصص بصيغة رسمية.

## طريقة الاعتماد داخل النظام

القوالب هي المصدر الرسمي لإخراج الكتب. عند إنشاء كتاب مرحلة داخلية أو تعديل بياناته من واجهة المراسلات، يقوم النظام بنسخ قالب Word، تعبئة الحقول، إدراج صورة QR، إدراج التوقيع الرقمي إن وجد، ثم حفظ ملف Word الناتج مع الوثيقة.

إذا عدلت قالب Word بعد أن كان هناك كتب مولدة سابقاً، فالملفات القديمة لن تتغير تلقائياً. افتح تفاصيل الكتاب واضغط `حفظ التعديلات وإعادة التوليد` حتى يتم إنشاء ملف جديد من القالب المعدل.

المعاينة داخل المتصفح تعتمد على وجود نسخة PDF مولدة من Word. إذا لم يظهر PDF، يبقى ملف Word الرسمي متاحاً للفتح أو التحميل، ويجب التأكد من عمل LibreOffice على الخادم لتحويل DOCX إلى PDF.

## حقول قالب كتاب المرحلة

عند تعديل قالب Word، ضع أسماء الحقول كنص عادي داخل الملف، مثلاً:

```text
{{registry_number}}
{{book_number}}
{{qr_code}}
{{gregorian_date}}
{{hijri_date}}
{{recipient}}
{{subject}}
{{body}}
{{signer_name}}
{{signer_role}}
{{signature}}
```

ملاحظات مهمة:

- `registry_number`: رقم الديوان الذي يكتبه المستخدم في الفورم.
- `book_number`: الرقم الداخلي المولد للـ QR، لا يلزم عرضه كنص داخل الكتاب.
- `qr_code`: مكان صورة QR. يتم إدراجها كصورة عادية في موضعها، ويمكن أن تحمل شعار المؤسسة أو نصاً مختصراً في الوسط دون تغيير قيمة الكود.
- `recipient`: سطر المخاطب مثل `إلى السيد ...`.
- `body`: محتوى الكتاب.
- `signer_name` و`signer_role`: اسم صاحب التوقيع وصفته.
- `signature`: صورة التوقيع الرقمي المحفوظة ضمن بيانات المستخدم، وتُترك فارغة إذا لم يحفظ توقيعاً بعد. يتم إدراجها كصورة عائمة فوق النص في مساحة التوقيع حتى لا تزاحم الفقرات ولا تؤثر على QR.

## إعدادات QR والتوقيع

تقرأ خدمة QR الإعدادات من:

```text
BackEnd/config/document_templates.php
```

- `QR_LOGO_PATH`: مسار شعار يوضع داخل QR. القيمة الافتراضية تستخدم شعار الفرونت `FrontEnd/src/assets/logo.jpg`.
- `QR_BRAND_TEXT`: نص بديل يظهر داخل QR عند غياب الشعار، والقيمة الافتراضية `CND`.

التوقيع يعالج داخل `DocxTemplateRenderer` كصورة Word عائمة `wp:anchor`، بينما QR يبقى `wp:inline`. هذا الفصل مهم حتى لا يؤدي إدراج التوقيع إلى اختفاء QR أو تغيير ترتيب النص حوله.

قبل إعادة بناء القوالب الرسمية أو تعديلها بشكل كبير، خذ نسخة احتياطية داخل:

```text
BackEnd/resources/templates/backups/
```

آخر نسخة احتياطية للقوالب الحالية موجودة في:

```text
BackEnd/resources/templates/backups/2026-06-20-signature-floating/
```

حالياً تم تجهيز مكان القوالب وربطه بإعدادات المشروع في:

```text
BackEnd/config/document_templates.php
```
