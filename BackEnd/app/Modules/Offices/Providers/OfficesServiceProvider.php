<?php
namespace App\Modules\Offices\Providers;

use App\Modules\Offices\Repositories\Eloquent\OfficeRepository;
use App\Modules\Offices\Repositories\Interfaces\OfficeRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class OfficesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OfficeRepositoryInterface::class, OfficeRepository::class);
    }
}
