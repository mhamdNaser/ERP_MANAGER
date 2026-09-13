<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['api', 'cnd.auth']]);

foreach (glob(app_path('Modules/*/Routes/api.php')) as $routeFile) {
    require $routeFile;
}
