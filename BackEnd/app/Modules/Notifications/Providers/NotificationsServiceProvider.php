<?php

namespace App\Modules\Notifications\Providers;

use App\Modules\Notifications\Repositories\Eloquent\NotificationRepository;
use App\Modules\Notifications\Repositories\Interfaces\NotificationRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
    }
}
