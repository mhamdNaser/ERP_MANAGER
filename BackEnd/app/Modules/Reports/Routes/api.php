<?php

use App\Modules\Reports\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('cnd.auth')->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->middleware('permission:reports.view');
    Route::post('reports', [ReportController::class, 'store'])->middleware('permission:reports.create');
    Route::put('reports/{report}', [ReportController::class, 'update']);
    Route::post('reports/{report}/transition', [ReportController::class, 'transition']);
});
