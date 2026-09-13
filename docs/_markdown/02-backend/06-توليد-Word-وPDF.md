# توليد وثائق Word و PDF

النظام يولّد كتباً رسمية بصيغة Word ثم يحوّلها إلى PDF للمعاينة داخل المتصفح.
لا يعتمد على PHPWord — المحرك مكتوب داخل المشروع.

---

## السلسلة الكاملة

```
        قالب DOCX جاهز
resources/templates/documents/...
              │
              ▼
   ┌──────────────────────────┐
   │  DocxTemplateRenderer    │  app/Services/Documents/
   │  ─────────────────────   │
   │  1. نسخ القالب للهدف      │
   │  2. فتحه كأرشيف ZIP       │
   │  3. استبدال {{المتغيرات}} │◄──── SimpleQrImage (صورة QR)
   │  4. إدراج الصور           │◄──── SignatureImage (التوقيع)
   │  5. تحديث العلاقات        │
   └───────────┬──────────────┘
               │  ملف DOCX جاهز
               ▼
   ┌──────────────────────────┐
   │   DocxPdfConverter       │  Modules/Communications/Services/
   │   ────────────────       │
   │   LibreOffice (الخادم)    │
   │   أو Word COM (ويندوز)    │
   └───────────┬──────────────┘
               │  ملف PDF
               ▼
   حفظ المسارين في السجل + روابط بوسم ?v=timestamp
```

---

## محرك القوالب — `app/Services/Documents/DocxTemplateRenderer.php`

### الفكرة الأساسية

ملف DOCX هو أرشيف ZIP يحوي ملفات XML. المحرك يفتحه بـ`ZipArchive`، يستبدل النص في
ملفات XML، ثم يعيد الحفظ.

```php
public function render(string $templatePath, string $targetPath, array $values, array $images = []): void
```

| المعامل | المعنى |
| --- | --- |
| `$templatePath` | مسار القالب الأصلي — **لا يُعدَّل أبداً** |
| `$targetPath` | مسار الملف الناتج |
| `$values` | `['subject' => 'الموضوع', ...]` تستبدل `{{subject}}` |
| `$images` | `['qr_code' => '/path/to.png']` أو مصفوفة مواصفات |

### الملفات المعالَجة داخل الأرشيف

```php
preg_match('/^(word|docProps)\/.*\.xml$/', $name)
```

كل XML تحت `word/` و`docProps/` — أي المتن والترويسة والتذييل وخصائص الملف.
هذا يعني أن `{{book_number}}` يعمل في ترويسة الصفحة كما في المتن.

### مشكلة تقسيم النص — والحل

Word يقسّم النص إلى أجزاء (runs) عند أي تغيير تنسيق. فقد يصبح `{{subject}}` في XML:

```xml
<w:r><w:t>{{sub</w:t></w:r><w:r><w:t>ject}}</w:t></w:r>
```

بحث نصي بسيط سيفشل. الدالة `replaceTextPlaceholder()` تعالج هذا.

> **قاعدة عملية عند تحرير القالب:** اكتب المتغيّر دفعة واحدة بلا تغيير خط أو لون
> في وسطه. إن لم يُستبدل متغيّر، احذفه واكتبه من جديد بنفس تنسيق ما حوله.

### إدراج الصور

عند وجود مفتاح في `$images` والعنصر النائب موجود في XML:

```php
$imageName = 'media/generated-' . $key . '-' . uniqid('', true) . '.png';
$zip->addFile($image['path'], 'word/' . $imageName);
$this->ensurePngContentType($zip);
$relationshipId = $this->addImageRelationship($zip, $imageName);
$xml = $this->replaceImagePlaceholder($xml, $placeholder, $relationshipId, $image);
```

ثلاث خطوات إلزامية في معيار OOXML:

| الخطوة | الدالة | لماذا |
| --- | --- | --- |
| إعلان نوع المحتوى | `ensurePngContentType()` | `[Content_Types].xml` يجب أن يعرف امتداد `png` |
| إنشاء علاقة | `addImageRelationship()` | `word/_rels/document.xml.rels` يربط `rId` بالملف |
| إدراج المرجع | `replaceImagePlaceholder()` | XML يشير إلى `rId` لا إلى المسار |

`addImageRelationship()` يقرأ أعلى `rId` موجود ويزيد واحداً، فلا يتعارض مع علاقات القالب.

### مواصفات الصورة

```php
$images['signature'] = [
    'path' => $signaturePath,
    'cx' => 2100000,          // العرض بوحدات EMU
    'cy' => 720000,           // الارتفاع بوحدات EMU
    'name' => 'digital-signature.png',
    'floating' => true,       // عائمة فوق النص
    'offset_y' => -260000,    // إزاحة رأسية
];
```

**EMU** (English Metric Unit): `1 سم = 360000 EMU`، `1 بوصة = 914400 EMU`.
الافتراضي `920000 × 920000` أي نحو 2.5 سم.

التوقيع `floating` افتراضياً بإزاحة سالبة، ليجلس فوق سطر الاسم لا تحته.

---

## توليد QR — `app/Services/Documents/SimpleQrImage.php`

```php
app(SimpleQrImage::class)->make($payload, $outputPath);
```

منفّذ يدوياً بلا مكتبات (210 أسطر). يدعم إدراج شعار المؤسسة في الوسط
مع الحفاظ على قابلية القراءة عبر تصحيح الأخطاء.

الحمولة المستخدمة:

| الوحدة | الصيغة | الملف |
| --- | --- | --- |
| طلبات HR | `CND-HR:{document_number}` | `Modules/Hr/Services/HrDocumentService.php` |
| المراسلات | `qr_payload` من الحدث | `Modules/Communications/Services/FormalDocumentService.php` |

---

## تجهيز التوقيع — `Modules/Communications/Services/SignatureImage.php`

```php
public function pngFor(?User $signer): ?string
{
    $path = $signer?->digital_signature_path;
    if (! $path || ! Storage::disk('public')->exists($path)) return null;

    $absolute = Storage::disk('public')->path($path);
    $info = @getimagesize($absolute);
    if (! $info) return null;
    if ($info[2] === IMAGETYPE_PNG) return $absolute;

    return $this->convertToPng($absolute, $info[2]);
}
```

> **لماذا التحويل؟** المحرك يدرج الملف باسم `.png` ويعلنه PNG في content-types.
> لو رفع الموقّع صورة JPEG سيظهر **مربع أسود** في العارض. الخدمة تكتشف الصيغة
> وتحوّل عند الحاجة. التعليق موثّق في رأس الملف نفسه.

`cleanup()` يحذف الملف المؤقت فقط (يفحص وجود `signature-converted-` في الاسم)
ولا يمسّ التوقيع الأصلي.

---

## التحويل إلى PDF — `Modules/Communications/Services/DocxPdfConverter.php`

### المسار الأول: LibreOffice (الخادم)

```php
$process = new Process([
    $binary, '--headless', '--norestore',
    '-env:UserInstallation=file://' . $profile,
    '--convert-to', 'pdf',
    '--outdir', dirname($absolutePath),
    $absolutePath,
], null, ['HOME' => $profile]);
$process->setTimeout(60);
```

> **`-env:UserInstallation` و`HOME` ضروريان.** LibreOffice يحتاج ملف تعريف قابلاً
> للكتابة، ومجلد بيت `www-data` ليس كذلك، فيفشل بالرمز **77**. الحل: ملف تعريف مخصص
> في `storage/app/private/libreoffice`. هذا موثّق كتعليق في الشيفرة.

### المسار الثاني: Microsoft Word (ويندوز محلياً)

شروط ثلاثة مجتمعة:

```php
app()->environment('local')
&& PHP_OS_FAMILY === 'Windows'
&& config('document_templates.local_word_pdf_fallback', true)
```

يشغّل PowerShell يفتح Word عبر COM ويحفظ بصيغة PDF (`SaveAs2` بالرمز `17`),
مع تحرير كائنات COM في `finally` حتى لا تبقى عمليات Word معلّقة.

### السلوك عند الفشل

**لا يُرمى استثناء.** الدالة ترجع `null`، ويُسجَّل تحذير. النتيجة: ملف Word يبقى
متاحاً للتنزيل، والمعاينة داخل المتصفح فقط هي التي تتعطل.

الموديلات تتعامل مع الغياب بأمان — من `app/Models/HrRequest.php`:

```php
private function fileUrl(?string $path): ?string
{
    if (! $path || ! Storage::disk('public')->exists($path)) return null;
    /* ... */
}
```

---

## القوالب

المسار: `BackEnd/resources/templates/documents/`

| المجلد | الملفات |
| --- | --- |
| `formal-correspondences/` | `stage-internal-letter.docx` (الأهم)، `stage-external-reply.docx`، `stage-study-report.docx`، `stage-execution-report.docx` |
| `hr/` | `request-approval.docx` |
| `messages/` | `message-export.docx` |
| `reports/` | `approved-report.docx` |
| `custom-forms/` | `submission-export.docx` |

المسارات مُعرّفة في `BackEnd/config/document_templates.php` وتُقرأ هكذا:

```php
$template = config('document_templates.hr.request_approval');
if (! $template || ! is_file($template)) return;    // لا وثيقة، لا خطأ
```

### متغيرات قالب الكتاب الرسمي

من `BackEnd/resources/templates/README.md`:

| المتغيّر | المحتوى |
| --- | --- |
| `{{registry_number}}` | رقم الديوان الذي يكتبه المستخدم |
| `{{book_number}}` | الرقم الداخلي المولَّد لأجل QR |
| `{{qr_code}}` | **صورة** — موضع QR |
| `{{gregorian_date}}` | التاريخ الميلادي |
| `{{hijri_date}}` | التاريخ الهجري |
| `{{recipient}}` | سطر المخاطب |
| `{{subject}}` | الموضوع |
| `{{body}}` | متن الكتاب |
| `{{signer_name}}` | اسم الموقّع |
| `{{signer_role}}` | صفته |
| `{{signature}}` | **صورة** — موضع التوقيع |

### تعديل قالب — الإجراء الصحيح

1. **خذ نسخة احتياطية** في `BackEnd/resources/templates/backups/`.
2. افتح القالب في Word وعدّل التنسيق.
3. اكتب المتغيرات كنص عادي بتنسيق موحّد.
4. احفظ بصيغة `.docx` (لا `.doc` ولا `.dotx`).
5. جرّب توليد وثيقة واحدة وافحص النتيجة.

> **الوثائق المولّدة سابقاً لا تتغيّر تلقائياً.** لتحديثها افتح تفاصيل الكتاب
> واضغط "حفظ التعديلات وإعادة التوليد".

---

## نمط التوليد الكامل — `Modules/Hr/Services/HrDocumentService.php`

```php
public function generate(HrRequest $request, ?User $signer): void
{
    $template = config('document_templates.hr.request_approval');
    if (! $template || ! is_file($template)) return;

    // 1. رقم وثيقة وحمولة QR
    $request->forceFill(['document_number' => $request->document_number ?: $this->nextNumber()])->save();
    $request->forceFill(['qr_payload' => 'CND-HR:' . $request->document_number])->save();

    // 2. مسار الإخراج منظّم زمنياً
    $relativePath = 'hr-requests/generated/' . now()->format('Y/m/d')
        . '/request-' . $request->id . '-' . uniqid() . '.docx';

    // 3. توليد QR في ملف مؤقت
    app(SimpleQrImage::class)->make($request->qr_payload, $temporaryQr);

    // 4. التوقيع إن وُجد، وإلا يُفرَّغ مكانه
    $signaturePath = $this->signature->pngFor($signer);
    if ($signaturePath) { $images['signature'] = [...]; } else { $values['signature'] = ''; }

    // 5. التوليد
    app(DocxTemplateRenderer::class)->render($template, $absolutePath, $values, $images);

    // 6. تنظيف المؤقتات
    @unlink($temporaryQr);
    $this->signature->cleanup($signaturePath);

    // 7. التحويل والحفظ
    $pdfAbsolute = $this->converter->convert($absolutePath);
    $request->forceFill(['word_path' => $relativePath, /* pdf_path */])->save();
}
```

اتبع هذه الخطوات السبع حرفياً عند إضافة نوع وثيقة جديد.

---

## حل المشاكل

| المشكلة | السبب المرجّح | الحل |
| --- | --- | --- |
| متغيّر يظهر كـ`{{name}}` | Word قسّمه إلى runs | احذفه واكتبه بتنسيق موحّد |
| مربع أسود مكان التوقيع | الصورة ليست PNG | `SignatureImage` يعالجها — تحقق أن `getimagesize` نجح |
| لا يُنشأ PDF على الخادم | LibreOffice يفشل بالرمز 77 | تحقق من صلاحيات `storage/app/private/libreoffice` |
| لا يُنشأ PDF محلياً | Word غير مثبّت أو `LOCAL_WORD_PDF_FALLBACK=false` | ثبّت LibreOffice أو فعّل المتغيّر |
| المتصفح يعرض نسخة قديمة | كاش | الروابط موسومة بـ`?v=timestamp` — تحقق أن `updated_at` تغيّر |
| لا تُولَّد وثيقة أصلاً | مسار القالب خاطئ | `is_file()` يفشل بصمت — افحص `config/document_templates.php` |
| عمليات Word معلّقة | فشل تحرير COM | السكربت يحرّرها في `finally`؛ أنهِ العمليات يدوياً إن لزم |
