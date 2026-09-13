<?php
namespace App\Modules\Hr\Providers;

use App\Modules\Hr\Repositories\Eloquent\HrRepository;
use App\Modules\Hr\Repositories\Interfaces\HrRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class HrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HrRepositoryInterface::class, HrRepository::class);
    }
}
