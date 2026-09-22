<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Modules\Locale\Providers\LocaleServiceProvider::class,
    App\Modules\Organization\Providers\OrganizationServiceProvider::class,
    App\Modules\Reports\Providers\ReportsServiceProvider::class,
    App\Modules\Notifications\Providers\NotificationsServiceProvider::class,
    App\Modules\Communications\Providers\CommunicationsServiceProvider::class,
    App\Modules\Employees\Providers\EmployeesServiceProvider::class,
    App\Modules\Permissions\Providers\PermissionsServiceProvider::class,
    App\Modules\Core\Providers\CoreServiceProvider::class,
    App\Modules\Offices\Providers\OfficesServiceProvider::class,
    App\Modules\Tasks\Providers\TasksServiceProvider::class,
    App\Modules\Forms\Providers\FormsServiceProvider::class,
    App\Modules\Hr\Providers\HrServiceProvider::class,
    App\Modules\Fleet\Providers\FleetServiceProvider::class,
    App\Modules\Drive\Providers\DriveServiceProvider::class,
    App\Modules\Database\Providers\DatabaseServiceProvider::class,
    App\Modules\Templates\Providers\TemplatesServiceProvider::class,
];
