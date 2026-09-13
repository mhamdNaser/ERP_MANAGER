<?php
namespace App\Modules\Forms\Providers;

use App\Modules\Forms\Repositories\Eloquent\FormRepository;
use App\Modules\Forms\Repositories\Interfaces\FormRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class FormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FormRepositoryInterface::class, FormRepository::class);
    }
}
