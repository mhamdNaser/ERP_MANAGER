<?php

namespace App\Modules\Communications\Providers;

use App\Modules\Communications\Repositories\Eloquent\CommunicationRepository;
use App\Modules\Communications\Repositories\Eloquent\FormalCorrespondenceRepository;
use App\Modules\Communications\Repositories\Interfaces\CommunicationRepositoryInterface;
use App\Modules\Communications\Repositories\Interfaces\FormalCorrespondenceRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class CommunicationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CommunicationRepositoryInterface::class, CommunicationRepository::class);
        $this->app->bind(FormalCorrespondenceRepositoryInterface::class, FormalCorrespondenceRepository::class);
    }
}
