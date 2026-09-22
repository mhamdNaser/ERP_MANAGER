<?php

namespace App\Modules\Templates\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Templates\Requests\UploadTemplateRequest;
use App\Modules\Templates\Services\TemplateRegistry;
use App\Modules\Templates\Services\TemplateStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentTemplateController extends Controller
{
    public function __construct(
        private TemplateRegistry $registry,
        private TemplateStorage $storage,
    ) {}

    public function index(): JsonResponse
    {
        $templates = array_map(function (array $template) {
            $template['backups'] = $this->storage->backups($template['key']);

            return $template;
        }, $this->registry->all());

        return response()->json(['templates' => $templates]);
    }

    public function download(string $key): BinaryFileResponse
    {
        $path = $this->found($key);

        return response()->download($path, basename($path));
    }

    /** النسخة الورقية الفارغة — للطباعة والتعبئة بالقلم. */
    public function blank(string $key): BinaryFileResponse
    {
        abort_unless($this->registry->has($key), 404, 'قالب غير معروف.');

        $path = $this->registry->blankPath($key);
        abort_unless($path, 404, 'لا توجد نسخة فارغة لهذا النموذج.');

        return response()->download($path, 'blank-' . basename($path));
    }

    public function upload(UploadTemplateRequest $request, string $key): JsonResponse
    {
        abort_unless($this->registry->has($key), 404, 'قالب غير معروف.');

        $file = $request->file('template');
        $missing = array_values(array_diff(
            $this->registry->entry($key)['fields'],
            $this->registry->placeholdersIn($file->getRealPath()),
        ));

        // قالبٌ ينقصه حقل يعني وثيقةً تخرج ناقصة. يُرفض ما لم يُصرّ الرافع.
        if ($missing && ! $request->boolean('force')) {
            return response()->json([
                'message' => 'القالب المرفوع ينقصه ' . count($missing) . ' حقل.',
                'missing' => $missing,
                'requires_force' => true,
            ], 422);
        }

        $result = $this->storage->replace($key, $file);

        return response()->json([
            'template' => $this->describe($key),
            'archived' => $result['archived'],
            'missing' => $missing,
        ]);
    }

    public function restore(Request $request, string $key): JsonResponse
    {
        abort_unless($this->registry->has($key), 404, 'قالب غير معروف.');

        $data = $request->validate([
            'backup' => ['required', 'string', 'max:64'],
        ]);

        $result = $this->storage->restore($key, $data['backup']);

        return response()->json([
            'template' => $this->describe($key),
            'archived' => $result['archived'],
        ]);
    }

    private function describe(string $key): array
    {
        $template = $this->registry->describe($key);
        $template['backups'] = $this->storage->backups($key);

        return $template;
    }

    private function found(string $key): string
    {
        abort_unless($this->registry->has($key), 404, 'قالب غير معروف.');

        $path = $this->registry->path($key);
        abort_unless($path && is_file($path), 404, 'ملف القالب غير موجود على الخادم.');

        return $path;
    }
}
