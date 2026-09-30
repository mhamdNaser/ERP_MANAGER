<?php

namespace App\Modules\Permissions\Providers;

use App\Models\User;
use App\Modules\Permissions\Repositories\Eloquent\PermissionRepository;
use App\Modules\Permissions\Repositories\Interfaces\PermissionRepositoryInterface;
use App\Modules\Permissions\Services\TabAccessService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PermissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
        $this->app->scoped(TabAccessService::class);
    }

    public function boot(): void
    {
        // البوابة الوحيدة للصلاحيات (بوابة spatie مُطفأة في config/permission.php):
        // جمهور التبويب أولاً — يمنح لمن فيه ويحجب عمّن خارجه — ثم صلاحيات الدور
        // كما كانت spatie تفحصها. null يترك القرار للسياسات.
        Gate::before(function ($user, string $ability, array $args = []) {
            if (! $user instanceof User) {
                return null;
            }
            $decision = $this->app->make(TabAccessService::class)->decide($user, $ability);
            if ($decision !== null) {
                return $decision;
            }
            $guard = is_string($args[0] ?? null) && ! class_exists($args[0]) ? $args[0] : null;

            return $user->checkPermissionTo($ability, $guard) ?: null;
        });
    }
}
