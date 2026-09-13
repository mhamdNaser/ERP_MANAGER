<?php
namespace App\Modules\Locale\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class LocaleController extends Controller
{
    public function setLocale(string $lang): JsonResponse
    {
        abort_unless(in_array($lang, ['ar', 'en'], true), 404);
        App::setLocale($lang);
        $payload = Cache::remember("translations_all_{$lang}_v1", 86400, function () use ($lang) {
            $admin = require resource_path("lang/{$lang}/admin.php");
            $messages = require resource_path("lang/{$lang}/messages.php");
            return array_merge($admin, $messages, ['admin' => $admin, 'messages' => $messages, '__meta' => ['language' => $lang, 'direction' => $lang === 'ar' ? 'RTL' : 'LTR']]);
        });
        return response()->json($payload)->header('Cache-Control', 'public, max-age=3600');
    }

    public function active(): JsonResponse
    {
        return response()->json([['name' => 'العربية', 'slug' => 'ar', 'direction' => 'RTL'], ['name' => 'English', 'slug' => 'en', 'direction' => 'LTR']]);
    }
}
