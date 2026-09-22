<?php

namespace App\Modules\Templates\Providers;

use App\Modules\Templates\Services\TemplateRegistry;
use App\Modules\Templates\Services\TemplateStorage;
use Illuminate\Support\ServiceProvider;

class TemplatesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // الكتالوج ثابت طوال الطلب، فنسخةٌ واحدة تكفي وتوفّر قراءة الملفات مراراً.
        $this->app->singleton(TemplateRegistry::class);
        $this->app->singleton(TemplateStorage::class);
    }
}
