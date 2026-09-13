<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    // أوامر الوحدات (Modules) لا تُكتشف تلقائيًا كـ app/Console/Commands — تُسجَّل هنا صراحة.
    ->withCommands([
        \App\Modules\Database\Commands\CreateAutomaticBackupCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'cnd.auth' => \App\Http\Middleware\AuthenticateApiToken::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
        $middleware->api(prepend: [\App\Http\Middleware\SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (
                ! $request->expectsJson()
                || $exception instanceof HttpExceptionInterface
                || $exception instanceof HttpResponseException
                || $exception instanceof AuthenticationException
                || $exception instanceof ValidationException
            ) {
                return null;
            }

            $errorId = (string) Str::uuid();

            Log::error('Unhandled API exception', [
                'error_id' => $errorId,
                'exception' => $exception,
            ]);

            $response = [
                'message' => 'حدث خطأ غير متوقع.',
                'error_id' => $errorId,
            ];

            if (config('app.debug')) {
                $response['exception'] = $exception::class;
                $response['error'] = $exception->getMessage();
                $response['file'] = $exception->getFile();
                $response['line'] = $exception->getLine();
                $response['trace'] = collect($exception->getTrace())->take(10)->all();
            }

            return response()->json($response, 500);
        });
    })->create();
