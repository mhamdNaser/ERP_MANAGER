<?php

namespace App\Services\Documents;

class SimpleQrImage
{
    public function make(string $value, string $path, int $modules = 29, int $scale = 8): void
    {
        $quiet = 4;
        $size = ($modules + ($quiet * 2)) * $scale;
        $image = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 17, 17, 17);

        imagefill($image, 0, 0, $white);

        $matrix = $this->matrix($value, $modules);
        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $on) {
                if (! $on) {
                    continue;
                }

                imagefilledrectangle(
                    $image,
                    ($x + $quiet) * $scale,
                    ($y + $quiet) * $scale,
                    (($x + $quiet + 1) * $scale) - 1,
                    (($y + $quiet + 1) * $scale) - 1,
                    $black,
                );
            }
        }

        $this->applyBrandMark($image, $size);
        imagepng($image, $path);
        imagedestroy($image);
    }

    private function applyBrandMark($image, int $size): void
    {
        $badgeSize = (int) round($size * 0.22);
        $badgeX = (int) (($size - $badgeSize) / 2);
        $badgeY = (int) (($size - $badgeSize) / 2);
        $radius = max(6, (int) round($badgeSize * 0.18));

        $white = imagecolorallocate($image, 255, 255, 255);
        $border = imagecolorallocate($image, 218, 228, 224);
        $green = imagecolorallocate($image, 10, 72, 65);

        $this->roundedRect($image, $badgeX - 4, $badgeY - 4, $badgeX + $badgeSize + 4, $badgeY + $badgeSize + 4, $radius + 4, $white);
        $this->roundedRectOutline($image, $badgeX - 4, $badgeY - 4, $badgeX + $badgeSize + 4, $badgeY + $badgeSize + 4, $radius + 4, $border);

        $logo = $this->loadLogo();
        if ($logo) {
            $logoWidth = imagesx($logo);
            $logoHeight = imagesy($logo);
            $sourceSize = min($logoWidth, $logoHeight);
            $sourceX = (int) (($logoWidth - $sourceSize) / 2);
            $sourceY = (int) (($logoHeight - $sourceSize) / 2);
            imagecopyresampled($image, $logo, $badgeX, $badgeY, $sourceX, $sourceY, $badgeSize, $badgeSize, $sourceSize, $sourceSize);
            imagedestroy($logo);
            return;
        }

        $this->roundedRect($image, $badgeX, $badgeY, $badgeX + $badgeSize, $badgeY + $badgeSize, $radius, $green);
        $text = $this->brandText();
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        imagestring(
            $image,
            $font,
            (int) (($size - $textWidth) / 2),
            (int) (($size - $textHeight) / 2),
            $text,
            $white,
        );
    }

    private function loadLogo()
    {
        $path = $this->logoPath();
        if (! $path || ! is_file($path)) {
            return null;
        }

        $type = function_exists('exif_imagetype')
            ? @exif_imagetype($path)
            : (@getimagesize($path)[2] ?? null);
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => null,
        };
    }

    private function logoPath(): ?string
    {
        if (function_exists('config')) {
            try {
                $configured = config('document_templates.qr.logo_path');
            } catch (\Throwable) {
                $configured = null;
            }
            if (is_string($configured) && $configured !== '') {
                return $configured;
            }
        }

        return null;
    }

    private function brandText(): string
    {
        if (function_exists('config')) {
            try {
                $configured = config('document_templates.qr.brand_text');
            } catch (\Throwable) {
                $configured = null;
            }
            if (is_string($configured) && $configured !== '') {
                return mb_substr($configured, 0, 6);
            }
        }

        return 'CND';
    }

    private function roundedRect($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    private function roundedRectOutline($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagesetthickness($image, 2);
        imageline($image, $x1 + $radius, $y1, $x2 - $radius, $y1, $color);
        imageline($image, $x1 + $radius, $y2, $x2 - $radius, $y2, $color);
        imageline($image, $x1, $y1 + $radius, $x1, $y2 - $radius, $color);
        imageline($image, $x2, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagearc($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color);
        imagearc($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color);
        imagearc($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color);
        imagearc($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $color);
        imagesetthickness($image, 1);
    }

    private function matrix(string $value, int $modules): array
    {
        $seed = $this->seed($value);
        $matrix = array_fill(0, $modules, array_fill(0, $modules, false));

        $this->finder($matrix, 0, 0);
        $this->finder($matrix, $modules - 7, 0);
        $this->finder($matrix, 0, $modules - 7);

        for ($y = 0; $y < $modules; $y += 1) {
            for ($x = 0; $x < $modules; $x += 1) {
                if ($this->insideFinder($x, $y, $modules)) {
                    continue;
                }

                if ($x === 6 || $y === 6) {
                    $matrix[$y][$x] = ($x + $y) % 2 === 0;
                    continue;
                }

                $seed = ($seed * 1103515245 + 12345 + ($x * 31) + $y) % 2147483647;
                $matrix[$y][$x] = ($seed % 5) !== 0;
            }
        }

        return $matrix;
    }

    private function finder(array &$matrix, int $left, int $top): void
    {
        for ($y = 0; $y < 7; $y += 1) {
            for ($x = 0; $x < 7; $x += 1) {
                $border = $x === 0 || $x === 6 || $y === 0 || $y === 6;
                $center = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;
                $matrix[$top + $y][$left + $x] = $border || $center;
            }
        }
    }

    private function insideFinder(int $x, int $y, int $modules): bool
    {
        return ($x < 8 && $y < 8)
            || ($x >= $modules - 8 && $y < 8)
            || ($x < 8 && $y >= $modules - 8);
    }

    private function seed(string $value): int
    {
        $seed = 0;
        foreach (mb_str_split($value ?: 'CND') as $char) {
            $seed = (($seed * 31) + crc32($char)) % 2147483647;
        }

        return max($seed, 1);
    }
}
