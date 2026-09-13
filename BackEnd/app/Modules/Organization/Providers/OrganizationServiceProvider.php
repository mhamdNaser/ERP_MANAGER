<?php
namespace App\Modules\Organization\Providers;

use App\Modules\Organization\Repositories\Eloquent\OrganizationRepository;
use App\Modules\Organization\Repositories\Interfaces\OrganizationRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class OrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrganizationRepositoryInterface::class, OrganizationRepository::class);
    }
}
