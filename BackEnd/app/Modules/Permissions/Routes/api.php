<?php

use App\Modules\Permissions\Controllers\PermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('cnd.auth')->group(function () {
    Route::get('roles-permissions', [PermissionController::class, 'index'])->middleware('permission:roles.permissions.manage');
    Route::put('roles-permissions/{role}', [PermissionController::class, 'update'])->middleware('permission:roles.permissions.manage');
});
