<?php

namespace App\Services\Documents;

use RuntimeException;
use ZipArchive;

class DocxTemplateRenderer
{
    private array $relationshipXml = [];

    public function render(string $templatePath, string $targetPath, array $values, array $images = []): void
    {
        $this->relationshipXml = [];

        if (! is_file($templatePath)) {
            throw new RuntimeException("Document template not found: {$templatePath}");
        }

        $directory = dirname($targetPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (! copy($templatePath, $targetPath)) {
            throw new RuntimeException("Unable to copy document template to: {$targetPath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($targetPath) !== true) {
            throw new RuntimeException("Unable to open generated DOCX: {$targetPath}");
        }

        foreach ($this->xmlEntries($zip) as $entry) {
            $xml = $zip->getFromName($entry);
            if ($xml === false) {
                continue;
            }

            foreach ($values as $key => $value) {
                $xml = $this->replaceTextPlaceholder($xml, '{{' . $key . '}}', (string) ($value ?? ''));
            }

            foreach ($images as $key => $path) {
                $image = $this->imageSpec($key, $path);
                $placeholder = '{{' . $key . '}}';
                if (! $this->hasPlaceholder($xml, $placeholder) || ! is_file($image['path'])) {
                    continue;
                }

                $imageName = 'media/generated-' . $key . '-' . uniqid('', true) . '.png';
                $zip->addFile($image['path'], 'word/' . $imageName);
                $this->ensurePngContentType($zip);
                $relationshipId = $this->addImageRelationship($zip, $imageName);
                $xml = $this->replaceImagePlaceholder($xml, $placeholder, $relationshipId, $image);
            }

            $zip->deleteName($entry);
            $zip->addFromString($entry, $xml);
        }

        $zip->close();
    }

    private function xmlEntries(ZipArchive $zip): array
    {
        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index += 1) {
            $name = $zip->getNameIndex($index);
            if ($name && preg_match('/^(word|docProps)\/.*\.xml$/', $name)) {
                $entries[] = $name;
            }
        }

        return $entries;
    }

    private function addImageRelationship(ZipArchive $zip, string $imageName): string
    {
        $entry = 'word/_rels/document.xml.rels';
        $xml = $this->relationshipXml[$entry]
            ?? $zip->getFromName($entry)
            ?: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
        preg_match_all('/Id="rId(\d+)"/', $xml, $matches);
        $next = collect($matches[1] ?? [])->map(fn ($id) => (int) $id)->max() + 1;
        $relationshipId = 'rId' . max($next, 1);
        $relationship = '<Relationship Id="' . $relationshipId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="' . $this->escape($imageName) . '"/>';
        $xml = str_replace('</Relationships>', $relationship . '</Relationships>', $xml);
        $this->relationshipXml[$entry] = $xml;

        $zip->deleteName($entry);
        $zip->addFromString($entry, $xml);

        return $relationshipId;
    }

    private function ensurePngContentType(ZipArchive $zip): void
    {
        $entry = '[Content_Types].xml';
        $xml = $zip->getFromName($entry);
        if ($xml === false || str_contains($xml, 'Extension="png"')) {
            return;
        }

        $default = '<Default Extension="png" ContentType="image/png"/>';
        $xml = str_replace('</Types>', $default . '</Types>', $xml);
        $zip->deleteName($entry);
        $zip->addFromString($entry, $xml);
    }

    private function imageSpec(string $key, mixed $value): array
    {
        if (is_array($value)) {
            return [
                'path' => $value['path'] ?? '',
                'cx' => (int) ($value['cx'] ?? 920000),
                'cy' => (int) ($value['cy'] ?? 920000),
                'name' => $value['name'] ?? $key . '.png',
                'floating' => (bool) ($value['floating'] ?? ($key === 'signature')),
                'offset_y' => (int) ($value['offset_y'] ?? ($key === 'signature' ? -260000 : 0)),
            ];
        }

        return [
            'path' => (string) $value,
            'cx' => 920000,
            'cy' => 920000,
            'name' => $key . '.png',
            'floating' => $key === 'signature',
            'offset_y' => $key === 'signature' ? -260000 : 0,
        ];
    }

    private function replaceImagePlaceholder(string $xml, string $placeholder, string $relationshipId, array $image): string
    {
        return $this->replaceRunPlaceholder($xml, $placeholder, fn () => $this->imageDrawing($relationshipId, $image));
    }

    private function replaceTextPlaceholder(string $xml, string $placeholder, string $value): string
    {
        return $this->replaceRunPlaceholder(
            $xml,
            $placeholder,
            fn (array $range) => $this->textRun($range['prefix'] . $this->escape($value) . $range['suffix'], $range['run']),
        );
    }

    private function hasPlaceholder(string $xml, string $placeholder): bool
    {
        return mb_strpos($this->runsText($xml), $placeholder) !== false;
    }

    private function replaceRunPlaceholder(string $xml, string $placeholder, callable $replacement): string
    {
        while (($range = $this->placeholderRunRange($xml, $placeholder)) !== null) {
            $xml = substr($xml, 0, $range['start_byte'])
                . $replacement($range)
                . substr($xml, $range['end_byte']);
        }

        return $xml;
    }

    private function placeholderRunRange(string $xml, string $placeholder): ?array
    {
        preg_match_all('/<w:r\b[^>]*>.*?<\/w:r>/s', $xml, $matches, PREG_OFFSET_CAPTURE);
        $runs = [];
        $text = '';

        foreach ($matches[0] as [$run, $byteOffset]) {
            $runText = $this->runText($run);
            if ($runText === '') {
                continue;
            }

            $start = mb_strlen($text);
            $text .= $runText;
            $runs[] = [
                'run' => $run,
                'byte_offset' => $byteOffset,
                'byte_end' => $byteOffset + strlen($run),
                'text' => $runText,
                'start' => $start,
                'end' => mb_strlen($text),
            ];
        }

        $position = mb_strpos($text, $placeholder);
        if ($position === false) {
            return null;
        }

        $endPosition = $position + mb_strlen($placeholder);
        $startRun = null;
        $endRun = null;

        foreach ($runs as $index => $run) {
            if ($startRun === null && $position >= $run['start'] && $position < $run['end']) {
                $startRun = $index;
            }
            if ($endPosition > $run['start'] && $endPosition <= $run['end']) {
                $endRun = $index;
                break;
            }
        }

        if ($startRun === null || $endRun === null) {
            return null;
        }

        $first = $runs[$startRun];
        $last = $runs[$endRun];
        $prefixLength = $position - $first['start'];
        $suffixStart = $endPosition - $last['start'];

        return [
            'run' => $first['run'],
            'prefix' => $this->escape(mb_substr($first['text'], 0, $prefixLength)),
            'suffix' => $this->escape(mb_substr($last['text'], $suffixStart)),
            'start_byte' => $first['byte_offset'],
            'end_byte' => $last['byte_end'],
        ];
    }

    private function runsText(string $xml): string
    {
        preg_match_all('/<w:r\b[^>]*>.*?<\/w:r>/s', $xml, $matches);

        return implode('', array_map(fn (string $run) => $this->runText($run), $matches[0] ?? []));
    }

    private function runText(string $run): string
    {
        preg_match_all('/<w:t\b[^>]*>(.*?)<\/w:t>/s', $run, $matches);

        return html_entity_decode(implode('', $matches[1] ?? []), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function textRun(string $text, string $sourceRun): string
    {
        preg_match('/<w:rPr\b[^>]*>.*?<\/w:rPr>/s', $sourceRun, $properties);
        $runProperties = $properties[0] ?? '';
        if ($this->containsArabic($text)) {
            $runProperties = $this->withRtlRunProperties($runProperties);
        }
        $lines = preg_split('/\R/u', $text);
        $content = '';

        foreach ($lines === false ? [$text] : $lines as $index => $line) {
            if ($index > 0) {
                $content .= '<w:br/>';
            }
            $content .= '<w:t xml:space="preserve">' . ($line === '' ? ' ' : $line) . '</w:t>';
        }

        return '<w:r>' . $runProperties . $content . '</w:r>';
    }

    private function containsArabic(string $text): bool
    {
        return preg_match('/\p{Arabic}/u', $text) === 1;
    }

    private function withRtlRunProperties(string $properties): string
    {
        if ($properties === '') {
            return '<w:rPr><w:rtl w:val="true"/><w:lang w:bidi="ar-SY"/></w:rPr>';
        }

        if (! str_contains($properties, '<w:rtl')) {
            $properties = str_replace('</w:rPr>', '<w:rtl w:val="true"/></w:rPr>', $properties);
        }

        if (! str_contains($properties, '<w:lang')) {
            $properties = str_replace('</w:rPr>', '<w:lang w:bidi="ar-SY"/></w:rPr>', $properties);
        }

        return $properties;
    }

    private function imageDrawing(string $relationshipId, array $image): string
    {
        $cx = (int) ($image['cx'] ?? 920000);
        $cy = (int) ($image['cy'] ?? 920000);
        $name = $this->escape($image['name'] ?? 'generated-image.png');
        $docPrId = $this->drawingId($relationshipId);

        if ($image['floating'] ?? false) {
            return $this->floatingImageDrawing($relationshipId, $image, $docPrId, $name, $cx, $cy);
        }

        return '<w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" distT="0" distB="0" distL="0" distR="0">'
            . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<wp:docPr id="' . $docPrId . '" name="' . $name . '"/>'
            . $this->pictureGraphic($relationshipId, $name, $cx, $cy)
            . '</wp:inline></w:drawing></w:r>';
    }

    private function floatingImageDrawing(string $relationshipId, array $image, int $docPrId, string $name, int $cx, int $cy): string
    {
        $offsetY = (int) ($image['offset_y'] ?? -260000);

        return '<w:r><w:drawing><wp:anchor xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" distT="0" distB="0" distL="0" distR="0" simplePos="0" relativeHeight="251659264" behindDoc="0" locked="0" layoutInCell="1" allowOverlap="1">'
            . '<wp:simplePos x="0" y="0"/>'
            . '<wp:positionH relativeFrom="column"><wp:align>center</wp:align></wp:positionH>'
            . '<wp:positionV relativeFrom="paragraph"><wp:posOffset>' . $offsetY . '</wp:posOffset></wp:positionV>'
            . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<wp:effectExtent l="0" t="0" r="0" b="0"/>'
            . '<wp:wrapNone/>'
            . '<wp:docPr id="' . $docPrId . '" name="' . $name . '"/>'
            . '<wp:cNvGraphicFramePr/>'
            . $this->pictureGraphic($relationshipId, $name, $cx, $cy)
            . '</wp:anchor></w:drawing></w:r>';
    }

    private function pictureGraphic(string $relationshipId, string $name, int $cx, int $cy): string
    {
        return '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:nvPicPr><pic:cNvPr id="0" name="' . $name . '"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="' . $relationshipId . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic>';
    }

    private function drawingId(string $relationshipId): int
    {
        if (preg_match('/(\d+)$/', $relationshipId, $matches)) {
            return max(1001, 1000 + (int) $matches[1]);
        }

        return random_int(1000, 999999);
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
