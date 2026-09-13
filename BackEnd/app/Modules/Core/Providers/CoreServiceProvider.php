<?php
namespace App\Modules\Core\Providers;

use App\Modules\Core\Repositories\Eloquent\CoreRepository;
use App\Modules\Core\Repositories\Interfaces\CoreRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CoreRepositoryInterface::class, CoreRepository::class);
    }
}
