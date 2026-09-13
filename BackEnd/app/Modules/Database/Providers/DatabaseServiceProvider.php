<?php
namespace App\Modules\Database\Providers;

use App\Modules\Database\Repositories\Eloquent\DatabaseRepository;
use App\Modules\Database\Repositories\Interfaces\DatabaseRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DatabaseRepositoryInterface::class, DatabaseRepository::class);
    }
}
