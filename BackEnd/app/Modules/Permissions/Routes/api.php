<?php

use App\Modules\Permissions\Controllers\PermissionController;
use App\Modules\Permissions\Controllers\TabAccessController;
use Illuminate\Support\Facades\Route;

Route::middleware('cnd.auth')->group(function () {
    Route::get('roles-permissions', [PermissionController::class, 'index'])->middleware('permission:roles.permissions.manage');
    Route::put('roles-permissions/{role}', [PermissionController::class, 'update'])->middleware('permission:roles.permissions.manage');

    Route::middleware('permission:roles.permissions.manage')->group(function () {
        Route::get('tab-access', [TabAccessController::class, 'index']);
        Route::post('tab-access', [TabAccessController::class, 'store']);
        Route::put('tab-access/{grant}', [TabAccessController::class, 'update']);
        Route::delete('tab-access/{grant}', [TabAccessController::class, 'destroy']);
    });
});
