<?php

namespace App\Modules\Templates\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

/**
 * استبدال ملف القالب في مكانه وباسمه، مع حفظ النسخة السابقة أولاً.
 *
 * القالب الجديد يحلّ محلّ القديم بالمسار والاسم نفسيهما، لأن المسار مكتوبٌ
 * في config/document_templates.php وتقرؤه خدمات التوليد؛ تغييره يعني تعديل كود.
 * والنسخة السابقة تُحفظ قبل الاستبدال كي يبقى التراجع ممكناً بضغطة.
 */
class TemplateStorage
{
    public function __construct(private TemplateRegistry $registry) {}

    /** مجلد النسخ السابقة لهذا القالب. */
    public function backupDirectory(string $key): string
    {
        $root = config('document_templates.backups_path') ?: resource_path('templates/backups');

        return rtrim((string) $root, '/\\') . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $key);
    }

    /** النسخ السابقة، الأحدث أولاً. */
    public function backups(string $key): array
    {
        $directory = $this->backupDirectory($key);
        if (! is_dir($directory)) {
            return [];
        }

        $items = [];
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.docx') ?: [] as $path) {
            $items[] = [
                'name' => basename($path),
                'size' => filesize($path),
                'created_at' => date('Y-m-d H:i:s', filemtime($path)),
            ];
        }

        usort($items, fn (array $a, array $b) => strcmp($b['name'], $a['name']));

        return $items;
    }

    public function backupPath(string $key, string $name): string
    {
        // اسم النسخة يأتي من العميل، فيُقصر على الاسم المجرّد بلا أي مسار.
        $safe = basename($name);
        if (! preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}(_[0-9]{1,3})?\.docx$/', $safe)) {
            throw new RuntimeException('اسم النسخة غير صالح.');
        }

        $path = $this->backupDirectory($key) . DIRECTORY_SEPARATOR . $safe;
        if (! is_file($path)) {
            throw new RuntimeException('النسخة المطلوبة غير موجودة.');
        }

        return $path;
    }

    /** يحفظ القالب الحالي في النسخ السابقة، ويعيد اسم النسخة إن وُجد ملف. */
    public function archiveCurrent(string $key): ?string
    {
        $path = $this->registry->path($key);
        if (! $path || ! is_file($path)) {
            return null;
        }

        $directory = $this->backupDirectory($key);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('تعذّر إنشاء مجلد النسخ السابقة.');
        }

        // استبدالان في الثانية نفسها يتشاركان الطابع الزمني، فيُميَّز الثاني
        // بلاحقة. الفاصل «_» مقصود: يرتّب اللاحقة بعد الاسم المجرّد لا قبله.
        $stamp = now()->format('Y-m-d_His');
        $name = $stamp . '.docx';
        for ($counter = 2; is_file($directory . DIRECTORY_SEPARATOR . $name) && $counter < 1000; $counter += 1) {
            $name = $stamp . '_' . $counter . '.docx';
        }

        if (! copy($path, $directory . DIRECTORY_SEPARATOR . $name)) {
            throw new RuntimeException('تعذّر حفظ نسخة من القالب الحالي.');
        }

        return $name;
    }

    /** يضع الملف المرفوع مكان القالب بالاسم نفسه، بعد أرشفة السابق. */
    public function replace(string $key, UploadedFile $file): array
    {
        $path = $this->registry->path($key);
        if (! $path) {
            throw new RuntimeException('لا مسار معرَّف لهذا القالب في الإعدادات.');
        }

        $this->assertDocx($file->getRealPath());

        $archived = $this->archiveCurrent($key);
        $this->ensureDirectory(dirname($path));

        if (! $file->move(dirname($path), basename($path))) {
            throw new RuntimeException('تعذّر كتابة القالب الجديد.');
        }

        return ['archived' => $archived];
    }

    /** يعيد نسخةً سابقة إلى مكان القالب، بعد أرشفة الحالي كي يبقى التراجع ممكناً. */
    public function restore(string $key, string $name): array
    {
        $backup = $this->backupPath($key, $name);
        $path = $this->registry->path($key);
        if (! $path) {
            throw new RuntimeException('لا مسار معرَّف لهذا القالب في الإعدادات.');
        }

        $archived = $this->archiveCurrent($key);
        $this->ensureDirectory(dirname($path));

        if (! copy($backup, $path)) {
            throw new RuntimeException('تعذّر استعادة النسخة.');
        }

        return ['archived' => $archived];
    }

    /** ملف Word صالح: حزمة zip فيها متن المستند. */
    private function assertDocx(?string $path): void
    {
        $zip = new ZipArchive();

        if (! $path || $zip->open($path) !== true) {
            throw new RuntimeException('الملف ليس مستند Word صالحاً (docx).');
        }

        $hasDocument = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        if (! $hasDocument) {
            throw new RuntimeException('الملف ليس مستند Word صالحاً (docx).');
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('تعذّر إنشاء مجلد القوالب.');
        }
    }
}
