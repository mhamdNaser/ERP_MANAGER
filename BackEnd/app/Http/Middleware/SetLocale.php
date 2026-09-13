<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('X-Language', config('app.locale'));
        App::setLocale(in_array($locale, ['ar', 'en'], true) ? $locale : config('app.fallback_locale'));
        return $next($request);
    }
}
