<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$outputDir = $root . '/docs';
$outputPath = $outputDir . '/CND-Manager-Arabic-Completion-Report.docx';

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

if (is_file($outputPath)) {
    unlink($outputPath);
}

$report = [
    'title' => 'تقرير إنجاز مشروع CND Manager',
    'subtitle' => 'تقرير إداري وتقني موجز عن مراحل التنفيذ والمشاكل والحلول',
    'date' => '20 حزيران 2026',
    'prepared_for' => 'الإدارة',
    'summary' => [
        ['المجال', 'النظام الداخلي لإدارة العمل المؤسسي'],
        ['التقنيات', 'Laravel 12, React 19, PostgreSQL'],
        ['أبرز الإضافات', 'مسار التقارير والخطط، المراسلات الرسمية، دليل الواجهات'],
        ['حالة التحقق', 'تم تشغيل فحوصات PHP وBuild الواجهة وتوليد التقرير'],
    ],
    'sections' => [
        [
            'title' => 'ملخص تنفيذي',
            'paragraphs' => [
                'تم تنفيذ نظام CND Manager كنظام داخلي متكامل لإدارة التقارير وخطط العمل والمهام والملفات والرسائل والمراسلات الرسمية والفورمات والموظفين والصلاحيات.',
                'ركز التنفيذ على ضبط الصلاحيات من الخادم، تنظيم الكود، تحسين تجربة الواجهة، وتوفير توثيق واضح يستطيع المطور أو المستخدم الرجوع إليه لاحقاً.',
            ],
        ],
        [
            'title' => 'نطاق العمل المنجز',
            'bullets' => [
                'تنظيم FrontEnd حسب التبويبات داخل مجلدات مستقلة، وفصل الربط المتكرر مع الباك إند داخل Apihooks.',
                'تنظيم BackEnd بنمط Modules مع Controllers وServices وModels وRoutes لكل وحدة.',
                'اعتماد React Query مع تفريغ الكاش عند تبديل الحسابات لضمان عدم اختلاط بيانات المستخدمين.',
                'تحديث دليل الواجهات وملفات README لتوثيق آخر الوظائف.',
                'توليد تقرير Word عربي قابل للعرض للإدارة وتحويله إلى PDF.',
            ],
        ],
        [
            'title' => 'التقارير وخطط العمل',
            'bullets' => [
                'تطبيق نفس مسار الاعتماد على التقارير وخطط العمل.',
                'رئيس القسم يستطيع الإرسال إلى مدير الفرع أو المدير العام أو الإعادة للموظف مع سبب.',
                'مدير الفرع يحول إلى المدير العام أو يعيد للموظف.',
                'بعد اعتماد المدير العام يتوقف زر الإعادة للموظف، وتبقى الرؤية للمدير العام ومدير قواعد البيانات.',
                'سبب الإعادة يحفظ مؤقتاً في كاش الباك إند، ويحذف عند إعادة إرسال نفس التقرير.',
                'إضافة فلاتر التاريخ والموظف والقسم والفرع حسب صلاحية الدور.',
            ],
        ],
        [
            'title' => 'المراسلات الرسمية وقوالب Word',
            'bullets' => [
                'فصل الرسائل الداخلية عن المراسلات الرسمية.',
                'تمثيل كل مراسلة كسلسلة زمنية تحتوي محطات ووثائق متعددة.',
                'توليد وثائق Word/PDF من قوالب رسمية.',
                'تحسين QR بإضافة لمسة بصرية أو شعار دون تغيير رقم الكود.',
                'إدراج التوقيع الرقمي كصورة عائمة داخل مساحة التوقيع حتى لا يزيح النص أو QR.',
                'إنشاء نسخ احتياطية للقوالب قبل التعديل عليها.',
            ],
        ],
    ],
    'problems' => [
        ['المشكلة', 'الأثر', 'الحل المعتمد'],
        ['React Query بعد تبديل الحساب', 'ظهور بيانات حساب سابق بعد تسجيل الدخول بحساب جديد', 'تفريغ كاش React Query عند تسجيل الدخول والخروج.'],
    ],
    'verification' => [
        ['نوع التحقق', 'النتيجة'],
        ['npm run build', 'نجح بعد تعديلات الواجهة.'],
        ['php -l', 'نجح لملفات الباك إند ومولّد التقرير.'],
        ['DOCX', 'تم فحص بنية word/document.xml والنص العربي.'],
    ],
    'recommendations' => [
        'يفضل نقل CSS الكبير تدريجياً إلى ملفات scoped حسب الصفحات.',
    ],
];

function xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function runText(string $text, array $options = []): string
{
    $bold = ! empty($options['bold']) ? '<w:b/><w:bCs/>' : '';
    $color = isset($options['color']) ? '<w:color w:val="' . xml($options['color']) . '"/>' : '';
    $size = (string) ($options['size'] ?? 24);
    $font = xml($options['font'] ?? 'Arial');

    return '<w:r><w:rPr><w:rtl/>' . $bold . $color . '<w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/><w:rFonts w:ascii="' . $font . '" w:hAnsi="' . $font . '" w:cs="' . $font . '"/></w:rPr><w:t xml:space="preserve">' . xml($text) . '</w:t></w:r>';
}

function paragraph(string $text, string $style = 'BodyText', array $options = []): string
{
    $align = $options['align'] ?? 'right';
    $before = (string) ($options['before'] ?? 0);
    $after = (string) ($options['after'] ?? 120);
    $spacing = '<w:spacing w:before="' . $before . '" w:after="' . $after . '" w:line="360" w:lineRule="auto"/>';

    return '<w:p><w:pPr><w:pStyle w:val="' . xml($style) . '"/><w:bidi/><w:jc w:val="' . xml($align) . '"/>' . $spacing . '</w:pPr>' . runText($text, $options) . '</w:p>';
}

function heading(string $text): string
{
    return '<w:p><w:pPr><w:pStyle w:val="Heading1"/><w:bidi/><w:jc w:val="right"/><w:spacing w:before="260" w:after="130"/><w:shd w:fill="E7F1EF"/><w:pBdr><w:bottom w:val="single" w:sz="10" w:space="4" w:color="0D655B"/></w:pBdr></w:pPr>' . runText($text, ['bold' => true, 'size' => 30, 'color' => '06312D']) . '</w:p>';
}

function bullet(string $text): string
{
    return '<w:p><w:pPr><w:pStyle w:val="ListParagraph"/><w:bidi/><w:jc w:val="right"/><w:spacing w:after="80" w:line="330" w:lineRule="auto"/><w:ind w:right="520" w:hanging="260"/></w:pPr>' . runText('• ' . $text, ['size' => 23]) . '</w:p>';
}

function pageBreak(): string
{
    return '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
}

function cell(string $text, array $options = []): string
{
    $fill = $options['fill'] ?? 'FFFFFF';
    $width = (string) ($options['width'] ?? 3000);
    $bold = ! empty($options['bold']);
    $color = $options['color'] ?? '12211F';

    return '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/><w:shd w:fill="' . xml($fill) . '"/><w:tcMar><w:top w:w="120" w:type="dxa"/><w:right w:w="140" w:type="dxa"/><w:bottom w:w="120" w:type="dxa"/><w:left w:w="140" w:type="dxa"/></w:tcMar></w:tcPr><w:p><w:pPr><w:bidi/><w:jc w:val="right"/></w:pPr>' . runText($text, ['bold' => $bold, 'size' => 22, 'color' => $color]) . '</w:p></w:tc>';
}

function tableXml(array $rows, array $widths): string
{
    $xml = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:bidiVisual/><w:tblW w:w="0" w:type="auto"/><w:tblBorders><w:top w:val="single" w:sz="6" w:color="D9E4E1"/><w:left w:val="single" w:sz="6" w:color="D9E4E1"/><w:bottom w:val="single" w:sz="6" w:color="D9E4E1"/><w:right w:val="single" w:sz="6" w:color="D9E4E1"/><w:insideH w:val="single" w:sz="6" w:color="D9E4E1"/><w:insideV w:val="single" w:sz="6" w:color="D9E4E1"/></w:tblBorders></w:tblPr>';

    foreach ($rows as $index => $row) {
        $isHeader = $index === 0;
        $xml .= '<w:tr>';
        foreach ($row as $cellIndex => $text) {
            $xml .= cell((string) $text, [
                'fill' => $isHeader ? '06312D' : ($index % 2 === 0 ? 'F8FBFA' : 'FFFFFF'),
                'bold' => $isHeader,
                'color' => $isHeader ? 'FFFFFF' : '12211F',
                'width' => $widths[$cellIndex] ?? 3000,
            ]);
        }
        $xml .= '</w:tr>';
    }

    return $xml . '</w:tbl>' . paragraph(' ', 'BodyText', ['after' => 80]);
}

function stylesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:rtl/></w:rPr></w:rPrDefault>
    <w:pPrDefault><w:pPr><w:bidi/><w:jc w:val="right"/><w:spacing w:line="360" w:lineRule="auto"/></w:pPr></w:pPrDefault>
  </w:docDefaults>
  <w:style w:type="paragraph" w:styleId="BodyText"><w:name w:val="Body Text"/><w:pPr><w:bidi/><w:jc w:val="right"/><w:spacing w:after="120" w:line="360" w:lineRule="auto"/></w:pPr><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="Heading 1"/><w:pPr><w:bidi/><w:jc w:val="right"/><w:keepNext/></w:pPr><w:rPr><w:b/><w:bCs/><w:color w:val="06312D"/><w:sz w:val="30"/><w:szCs w:val="30"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/><w:pPr><w:bidi/><w:jc w:val="right"/></w:pPr><w:rPr><w:sz w:val="23"/><w:szCs w:val="23"/></w:rPr></w:style>
  <w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/><w:tblPr><w:tblBorders><w:top w:val="single" w:sz="4" w:color="D9E4E1"/><w:left w:val="single" w:sz="4" w:color="D9E4E1"/><w:bottom w:val="single" w:sz="4" w:color="D9E4E1"/><w:right w:val="single" w:sz="4" w:color="D9E4E1"/><w:insideH w:val="single" w:sz="4" w:color="D9E4E1"/><w:insideV w:val="single" w:sz="4" w:color="D9E4E1"/></w:tblBorders></w:tblPr></w:style>
</w:styles>';
}

$body = '';
$body .= '<w:p><w:pPr><w:bidi/><w:jc w:val="center"/><w:spacing w:before="1200" w:after="240"/></w:pPr>' . runText($report['title'], ['bold' => true, 'size' => 44, 'color' => '06312D']) . '</w:p>';
$body .= '<w:p><w:pPr><w:bidi/><w:jc w:val="center"/><w:spacing w:after="520"/></w:pPr>' . runText($report['subtitle'], ['size' => 28, 'color' => '51635F']) . '</w:p>';
$body .= tableXml([
    ['البند', 'القيمة'],
    ['الجهة المستفيدة', $report['prepared_for']],
    ['تاريخ التقرير', $report['date']],
    ['اسم النظام', 'CND Manager'],
], [2500, 5600]);
$body .= pageBreak();

$body .= heading('بطاقة ملخص المشروع');
$body .= tableXml($report['summary'], [2600, 6200]);

foreach ($report['sections'] as $section) {
    $body .= heading($section['title']);
    foreach ($section['paragraphs'] ?? [] as $paragraph) {
        $body .= paragraph($paragraph);
    }
    foreach ($section['bullets'] ?? [] as $item) {
        $body .= bullet($item);
    }
}

$body .= heading('جدول المشاكل والحلول');
$body .= tableXml($report['problems'], [2200, 3000, 3900]);

$body .= heading('جدول التحقق والجودة');
$body .= tableXml($report['verification'], [3000, 6000]);

$body .= heading('توصيات لاحقة');
foreach ($report['recommendations'] as $item) {
    $body .= bullet($item);
}

$documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>' . $body . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="900" w:right="1000" w:bottom="900" w:left="1000"/><w:bidi/></w:sectPr></w:body>
</w:document>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
</Types>';

$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';

$documentRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>';

$settingsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:defaultTabStop w:val="720"/><w:themeFontLang w:val="en-US" w:bidi="ar-SA"/></w:settings>';

$zip = new ZipArchive();
if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Unable to create DOCX file.');
}

$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addEmptyDir('_rels');
$zip->addFromString('_rels/.rels', $rels);
$zip->addEmptyDir('word');
$zip->addEmptyDir('word/_rels');
$zip->addFromString('word/document.xml', $documentXml);
$zip->addFromString('word/_rels/document.xml.rels', $documentRels);
$zip->addFromString('word/styles.xml', stylesXml());
$zip->addFromString('word/settings.xml', $settingsXml);
$zip->close();

echo $outputPath . PHP_EOL;
