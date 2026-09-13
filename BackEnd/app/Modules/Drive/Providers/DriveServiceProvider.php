<?php
namespace App\Modules\Drive\Providers;

use App\Modules\Drive\Repositories\Eloquent\DriveRepository;
use App\Modules\Drive\Repositories\Interfaces\DriveRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class DriveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DriveRepositoryInterface::class, DriveRepository::class);
    }
}
