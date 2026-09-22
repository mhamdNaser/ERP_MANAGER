<?php
namespace App\Modules\Fleet\Providers;

use App\Modules\Fleet\Repositories\Eloquent\FleetRepository;
use App\Modules\Fleet\Repositories\Interfaces\FleetRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class FleetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FleetRepositoryInterface::class, FleetRepository::class);
    }
}
