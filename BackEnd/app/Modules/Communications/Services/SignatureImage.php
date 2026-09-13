<?php

namespace App\Modules\Communications\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * يجهّز صورة التوقيع للإدراج في القالب.
 *
 * القالب يدرج الملف باسم .png ويعلنه PNG في content-types، فأي ملف بصيغة أخرى
 * (JPEG مثلاً) يظهر مربعاً أسود عند العارض. لذلك نتحقق من الصيغة ونحوّل عند الحاجة.
 */
class SignatureImage
{
    /** @return string|null مسار PNG صالح، أو null إن لم يكن للموقّع توقيع. */
    public function pngFor(?User $signer): ?string
    {
        $path = $signer?->digital_signature_path;
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $absolute = Storage::disk('public')->path($path);
        $info = @getimagesize($absolute);
        if (! $info) {
            return null;
        }

        if ($info[2] === IMAGETYPE_PNG) {
            return $absolute;
        }

        return $this->convertToPng($absolute, $info[2]);
    }

    /** يحذف الملف المؤقت فقط، ولا يمس التوقيع الأصلي. */
    public function cleanup(?string $path): void
    {
        if ($path && str_contains($path, 'signature-converted-')) {
            @unlink($path);
        }
    }

    private function convertToPng(string $absolute, int $type): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolute),
            IMAGETYPE_GIF => @imagecreatefromgif($absolute),
            IMAGETYPE_WEBP => @imagecreatefromwebp($absolute),
            IMAGETYPE_BMP => @imagecreatefrombmp($absolute),
            default => null,
        };

        if (! $image) {
            return null;
        }

        $target = storage_path('app/private/tmp/signature-converted-' . uniqid() . '.png');
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $saved = imagepng($image, $target);
        imagedestroy($image);

        return $saved ? $target : null;
    }
}
