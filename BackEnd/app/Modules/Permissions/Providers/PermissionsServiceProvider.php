<?php

namespace App\Modules\Permissions\Providers;

use App\Modules\Permissions\Repositories\Eloquent\PermissionRepository;
use App\Modules\Permissions\Repositories\Interfaces\PermissionRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class PermissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
    }
}
