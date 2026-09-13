<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Repositories\Interfaces\CoreRepositoryInterface;
use App\Modules\Core\Requests\LoginRequest;
use App\Modules\Core\Services\DashboardAnalyticsService;
use App\Modules\Employees\Resources\UserResource;
use App\Modules\Notifications\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Modules\Notifications\Resources\NotificationResource;
use App\Modules\Reports\Repositories\Interfaces\ReportRepositoryInterface;
use App\Modules\Reports\Resources\ReportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SystemController extends Controller
{
    public function __construct(
        private CoreRepositoryInterface $core,
        private ReportRepositoryInterface $reports,
        private NotificationRepositoryInterface $notifications,
        private DashboardAnalyticsService $analytics,
    ) {}

    public function health(): JsonResponse
    {
        $started = microtime(true);

        if (! $this->core->databaseReachable()) {
            return response()->json(['status' => 'unavailable', 'server' => __('messages.connected'), 'database' => __('messages.disconnected_f'), 'message' => __('messages.database_unavailable')], 503);
        }

        $storage = collect(['local', 'public'])->mapWithKeys(function (string $disk) {
            $probe = ".health/{$disk}-write-probe";
            $contents = 'ok-' . Str::uuid();

            try {
                $filesystem = Storage::disk($disk);
                $written = $filesystem->put($probe, $contents);
                $readable = $written && $filesystem->get($probe) === $contents;

                if (! $written || ! $readable) {
                    throw new \RuntimeException("Storage disk [{$disk}] failed its write probe.");
                }

                try {
                    $filesystem->delete($probe);
                } catch (Throwable) {
                }

                return [$disk => ['writable' => true]];
            } catch (Throwable $exception) {
                report($exception);

                return [$disk => ['writable' => false]];
            }
        })->all();

        $ready = collect($storage)->every(fn(array $disk) => $disk['writable']);

        return response()->json([
            'status' => $ready ? 'ready' : 'unavailable',
            'server' => __('messages.connected'),
            'database' => __('messages.connected_f'),
            'connection' => $this->core->connectionName(),
            'storage' => $storage,
            'upload_limits' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_file_uploads' => ini_get('max_file_uploads'),
            ],
            'latency_ms' => round((microtime(true) - $started) * 1000),
            'checked_at' => now()->toIso8601String(),
        ], $ready ? 200 : 503);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->core->findActiveByEmail($request->validated('email'));
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) return response()->json(['message' => __('messages.invalid_credentials')], 422);
        $plainToken = Str::random(64);
        $this->core->setApiToken($user, hash('sha256', $plainToken));
        return response()->json(['token' => $plainToken, 'user' => new UserResource($user)]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($this->core->loadProfile($request->user()));
    }

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json([
            'stats' => $this->reports->statistics($request->user()),
            'analytics' => $this->analytics->for($request->user()),
            'recent_reports' => ReportResource::collection($this->reports->visibleTo($request->user(), 6)),
            'notifications' => NotificationResource::collection($this->notifications->recentFor($request->user(), 6)),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->core->setApiToken($request->user(), null);
        return response()->json(['message' => __('messages.logged_out')]);
    }
}
