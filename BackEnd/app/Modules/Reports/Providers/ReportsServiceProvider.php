<?php

namespace App\Modules\Reports\Providers;

use App\Modules\Reports\Repositories\Eloquent\ReportRepository;
use App\Modules\Reports\Repositories\Interfaces\ReportRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class ReportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportRepositoryInterface::class, ReportRepository::class);
    }
}
