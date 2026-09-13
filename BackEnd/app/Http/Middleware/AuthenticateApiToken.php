<?php
namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $user = $token ? User::where('api_token', hash('sha256', $token))->where('is_active', true)->first() : null;

        if (! $user) {
            return response()->json(['message' => 'انتهت الجلسة أو لا تملك صلاحية الوصول.'], 401);
        }

        $request->setUserResolver(fn () => $user);
        Auth::setUser($user);
        return $next($request);
    }
}
