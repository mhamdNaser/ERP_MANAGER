<?php

use App\Modules\Offices\Controllers\OfficeController;
use Illuminate\Support\Facades\Route;

Route::middleware('cnd.auth')->group(function () {
    Route::get('offices', [OfficeController::class, 'index'])->middleware('permission:offices.manage');
    Route::post('offices', [OfficeController::class, 'store'])->middleware('permission:offices.manage');
    Route::put('offices/{office}', [OfficeController::class, 'update'])->middleware('permission:offices.manage');
    Route::delete('offices/{office}', [OfficeController::class, 'destroy'])->middleware('permission:offices.manage');
});
