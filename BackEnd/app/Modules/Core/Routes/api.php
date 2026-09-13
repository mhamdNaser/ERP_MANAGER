<?php

use App\Modules\Core\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

// Neutral alias — some ad-block/antivirus web filters block URLs containing
// "system/health" (they resemble telemetry beacons), yielding a client-side
// ERR_BLOCKED_BY_CLIENT before the request ever leaves the browser.
Route::get('status', [SystemController::class, 'health']);
Route::get('system/health', [SystemController::class, 'health']);
Route::post('auth/login', [SystemController::class, 'login']);

Route::middleware('cnd.auth')->group(function () {
    Route::get('auth/me', [SystemController::class, 'me']);
    Route::post('auth/logout', [SystemController::class, 'logout']);
    Route::get('dashboard', [SystemController::class, 'dashboard']);
});
