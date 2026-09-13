<?php

use App\Modules\Hr\Controllers\HrRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('cnd.auth')->group(function () {
    Route::get('hr/my-requests', [HrRequestController::class, 'mine']);
    Route::post('hr/requests', [HrRequestController::class, 'store'])->middleware('permission:hr.request');
    Route::post('hr/requests/{hrRequest}/decision', [HrRequestController::class, 'decide']);
    Route::post('hr/requests/{hrRequest}/cancel', [HrRequestController::class, 'cancel']);
    Route::get('hr/requests', [HrRequestController::class, 'index'])->middleware('permission:hr.view');
    Route::put('hr/balances/{employee}', [HrRequestController::class, 'updateBalance'])->middleware('permission:hr.manage');
});
