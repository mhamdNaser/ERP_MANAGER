<?php

use App\Modules\Fleet\Controllers\FleetMissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('cnd.auth')->group(function () {
    Route::get('fleet/my-missions', [FleetMissionController::class, 'mine']);
    Route::post('fleet/missions', [FleetMissionController::class, 'store'])->middleware('permission:fleet.request');
    Route::post('fleet/missions/{fleetMission}/decision', [FleetMissionController::class, 'decide']);
    Route::post('fleet/missions/{fleetMission}/cancel', [FleetMissionController::class, 'cancel']);
    Route::get('fleet/missions', [FleetMissionController::class, 'index'])->middleware('permission:fleet.view');
});
