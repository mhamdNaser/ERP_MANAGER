<?php
namespace App\Modules\Tasks\Providers;

use App\Modules\Tasks\Repositories\Eloquent\TaskRepository;
use App\Modules\Tasks\Repositories\Interfaces\TaskRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class TasksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TaskRepositoryInterface::class, TaskRepository::class);
    }
}
