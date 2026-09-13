<?php

$base = dirname(__DIR__);
$force = in_array('--force', $argv, true);
$templates = [
    'resources/templates/documents/formal-correspondences/stage-internal-letter.docx' => [
        'title' => 'كتاب مرحلة داخلية',
        'subject' => '{{subject}}',
        'body' => '{{body}}',
        'recipient' => '{{recipient}}',
    ],
    'resources/templates/documents/formal-correspondences/stage-external-reply.docx' => [
        'title' => 'كتاب رد خارجي',
        'subject' => '{{subject}}',
        'body' => '{{body}}',
        'recipient' => '{{recipient}}',
    ],
    'resources/templates/documents/formal-correspondences/stage-study-report.docx' => [
        'title' => 'نموذج دراسة',
        'subject' => 'الموضوع: {{subject}}',
        'body' => "{{body}}\n\nالتوصية: {{recommendation}}",
        'recipient' => '{{recipient}}',
    ],
    'resources/templates/documents/formal-correspondences/stage-execution-report.docx' => [
        'title' => 'نموذج تنفيذ',
        'subject' => 'الموضوع: {{subject}}',
        'body' => "{{body}}\n\nنتيجة التنفيذ: {{recommendation}}",
        'recipient' => '{{recipient}}',
    ],
    'resources/templates/documents/hr/request-approval.docx' => [
        'title' => 'قرار الموافقة على طلب',
        'subject' => 'الموضوع: {{subject}}',
        'body' => "{{body}}

القرار: {{status}}",
        'recipient' => '{{recipient}}',
    ],
    'resources/templates/documents/messages/message-export.docx' => [
        'title' => 'تصدير رسالة داخلية',
        'subject' => '{{subject}}',
        'body' => "{{body}}\n\nالمرسل: {{source}}\nالمستلم: {{target}}",
        'recipient' => '{{recipient}}',
    ],
    'resources/templates/documents/reports/approved-report.docx' => [
        'title' => 'تقرير معتمد',
        'subject' => '{{subject}}',
        'body' => "{{body}}\n\nحالة الاعتماد: {{status}}",
        'recipient' => '{{recipient}}',
    ],
    'resources/templates/documents/custom-forms/submission-export.docx' => [
        'title' => 'تصدير تعبئة فورم',
        'subject' => '{{subject}}',
        'body' => "{{body}}\n\nالموظف: {{signer_name}}",
        'recipient' => '{{recipient}}',
    ],
];

$logo = $base . '/../FrontEnd/src/assets/syrian-eagle.png';

foreach ($templates as $relativePath => $data) {
    $path = $base . '/' . $relativePath;
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }

    if (is_file($path) && ! $force) {
        echo "kept {$relativePath}\n";
        continue;
    }

    buildDocx($path, $data, is_file($logo) ? $logo : null);
    echo "created {$relativePath}\n";
}

function buildDocx(string $path, array $data, ?string $logo): void
{
    if (is_file($path)) {
        unlink($path);
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE) !== true) {
        throw new RuntimeException("Unable to create {$path}");
    }

    $zip->addFromString('[Content_Types].xml', contentTypes($logo !== null));
    $zip->addFromString('_rels/.rels', packageRels());
    $zip->addFromString('docProps/core.xml', coreProps($data['title']));
    $zip->addFromString('docProps/app.xml', appProps());
    $zip->addFromString('word/_rels/document.xml.rels', documentRels($logo !== null));
    $zip->addFromString('word/document.xml', documentXml($data, $logo !== null));

    if ($logo !== null) {
        $zip->addFile($logo, 'word/media/syrian-eagle.png');
    }

    $zip->close();
}

function contentTypes(bool $hasLogo): string
{
    $png = $hasLogo ? '<Default Extension="png" ContentType="image/png"/>' : '';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . $png
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '</Types>';
}

function packageRels(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>';
}

function documentRels(bool $hasLogo): string
{
    $logo = $hasLogo
        ? '<Relationship Id="rIdLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/syrian-eagle.png"/>'
        : '';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $logo . '</Relationships>';
}

function coreProps(string $title): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title>' . xml($title) . '</dc:title>'
        . '</cp:coreProperties>';
}

function appProps(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>CND Manager</Application></Properties>';
}

function documentXml(array $data, bool $hasLogo): string
{
    $logoRun = $hasLogo ? imageRun('rIdLogo', 1050000, 1050000) : textRun('');
    $bodyBlock = bodyBlock($data['body']);

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
        . '<w:body>'
        . '<w:tbl><w:tblPr><w:tblW w:w="10000" w:type="pct"/><w:tblBorders><w:bottom w:val="single" w:sz="8" w:space="0" w:color="222222"/></w:tblBorders></w:tblPr><w:tr>'
        . cell(paragraph('Syrian Arab Republic' . "\n" . 'Ministry of Interior' . "\n" . 'Communications and Network' . "\n" . 'Management', 'left', true, 20, false, 0, 0, false))
        . cell(paragraphRaw($logoRun, 'center', 0, 0, false))
        . cell(paragraph('الجمهورية العربية السورية' . "\n" . 'وزارة الداخلية' . "\n" . 'إدارة الاتصالات والشبكات', 'right', true, 20))
        . '</w:tr></w:tbl>'
        . '<w:p/>'
        . '<w:tbl><w:tblPr><w:tblW w:w="10000" w:type="pct"/></w:tblPr><w:tr>'
        . cell(paragraph('الرقم: {{registry_number}}', 'right', true, 22))
        . cell(paragraph('التاريخ: {{gregorian_date}}' . "\n" . 'الموافق: {{hijri_date}}', 'left', true, 22))
        . '</w:tr></w:tbl>'
        . '<w:p/>'
        . paragraph($data['recipient'], 'center', true, 30)
        . paragraph('', 'right', false, 12, false, 420, 120)
        . $bodyBlock
        . '<w:tbl><w:tblPr><w:tblW w:w="10000" w:type="pct"/><w:tblCellMar><w:top w:w="90" w:type="dxa"/><w:left w:w="90" w:type="dxa"/><w:bottom w:w="90" w:type="dxa"/><w:right w:w="90" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tr><w:trPr><w:trHeight w:val="1700" w:hRule="atLeast"/></w:trPr>'
        . cell(paragraph('{{qr_code}}', 'left', false, 18, false, 0, 0, false), 3600)
        . cell(paragraph('{{signer_name}}' . "\n" . '{{signer_role}}' . "\n" . '{{signature}}' . "\n\n" . 'التوقيع والختم', 'center', true, 24), 6400)
        . '</w:tr></w:tbl>'
        . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="900" w:right="850" w:bottom="700" w:left="850"/><w:pgBorders><w:top w:val="single" w:sz="8" w:space="18" w:color="222222"/><w:left w:val="single" w:sz="8" w:space="18" w:color="222222"/><w:bottom w:val="single" w:sz="8" w:space="18" w:color="222222"/><w:right w:val="single" w:sz="8" w:space="18" w:color="222222"/></w:pgBorders></w:sectPr>'
        . '</w:body></w:document>';
}

function bodyBlock(string $body): string
{
    $paragraphs = preg_split('/\R/u', $body) ?: [$body];

    return '<w:tbl><w:tblPr><w:tblW w:w="10000" w:type="pct"/><w:tblCellMar><w:top w:w="80" w:type="dxa"/><w:left w:w="120" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/><w:right w:w="120" w:type="dxa"/></w:tblCellMar></w:tblPr>'
        . '<w:tr><w:trPr><w:trHeight w:val="5600" w:hRule="atLeast"/></w:trPr>'
        . cell(implode('', array_map(fn (string $line) => paragraph($line === '' ? ' ' : $line, 'right', false, 26), $paragraphs)), 10000)
        . '</w:tr></w:tbl>';
}

function cell(string $content, int $width = 5000): string
{
    return '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="pct"/></w:tcPr>' . $content . '</w:tc>';
}

function paragraph(string $text, string $align = 'right', bool $bold = false, int $size = 24, bool $underline = false, int $before = 0, int $after = 0, bool $rtl = true): string
{
    $runs = [];
    foreach (explode("\n", $text) as $index => $line) {
        if ($index > 0) {
            $runs[] = '<w:r><w:br/></w:r>';
        }
        $runs[] = textRun($line, $bold, $size, $underline, $rtl);
    }

    return paragraphRaw(implode('', $runs), $align, $before, $after, $rtl);
}

function paragraphRaw(string $content, string $align = 'right', int $before = 0, int $after = 0, bool $rtl = true): string
{
    $spacing = ($before || $after) ? '<w:spacing w:before="' . $before . '" w:after="' . $after . '"/>' : '';
    $bidi = $rtl ? '<w:bidi w:val="1"/>' : '';

    return '<w:p><w:pPr>' . $bidi . '<w:jc w:val="' . xml($align) . '"/>' . $spacing . '</w:pPr>' . $content . '</w:p>';
}

function textRun(string $text, bool $bold = false, int $size = 24, bool $underline = false, bool $rtl = true): string
{
    $props = '<w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/>'
        . ($bold ? '<w:b/><w:bCs/>' : '')
        . ($underline ? '<w:u w:val="single"/>' : '')
        . ($rtl ? '<w:rtl w:val="true"/><w:lang w:bidi="ar-SY"/>' : '')
        . '</w:rPr>';

    return '<w:r>' . $props . '<w:t xml:space="preserve">' . xml($text) . '</w:t></w:r>';
}

function imageRun(string $relationshipId, int $cx, int $cy): string
{
    return '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
        . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/><wp:docPr id="1" name="Logo"/>'
        . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="0" name="logo.png"/><pic:cNvPicPr/></pic:nvPicPr>'
        . '<pic:blipFill><a:blip r:embed="' . xml($relationshipId) . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
        . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
}

function xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}
