<?php

use App\Modules\Notifications\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('cnd.auth')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->middleware('permission:notifications.view');
    Route::get('notifications/{notification}', [NotificationController::class, 'show'])->middleware('permission:notifications.view');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->middleware('permission:notifications.view');
});
