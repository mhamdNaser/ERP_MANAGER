<?php

namespace App\Modules\Employees\Providers;

use App\Modules\Employees\Repositories\Eloquent\EmployeeRepository;
use App\Modules\Employees\Repositories\Interfaces\EmployeeRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class EmployeesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EmployeeRepositoryInterface::class, EmployeeRepository::class);
    }
}
