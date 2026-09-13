<?php

use App\Modules\Organization\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware('cnd.auth')->group(function () {
    Route::get('organization', [OrganizationController::class, 'index'])->middleware('permission:organization.view');
    Route::post('branches', [OrganizationController::class, 'storeBranch'])->middleware('permission:branches.create');
    Route::put('branches/{branch}', [OrganizationController::class, 'updateBranch'])->middleware('permission:branches.update');
    Route::delete('branches/{branch}', [OrganizationController::class, 'destroyBranch'])->middleware('permission:branches.delete');
    Route::post('departments', [OrganizationController::class, 'storeDepartment'])->middleware('permission:departments.create');
    Route::put('departments/{department}', [OrganizationController::class, 'updateDepartment'])->middleware('permission:departments.update');
    Route::delete('departments/{department}', [OrganizationController::class, 'destroyDepartment'])->middleware('permission:departments.delete');
});
