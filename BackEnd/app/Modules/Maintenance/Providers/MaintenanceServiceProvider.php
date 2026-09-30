<?php
namespace App\Modules\Maintenance\Providers;

use App\Modules\Maintenance\Repositories\Eloquent\MaintenanceRepository;
use App\Modules\Maintenance\Repositories\Interfaces\MaintenanceRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class MaintenanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MaintenanceRepositoryInterface::class, MaintenanceRepository::class);
    }
}
