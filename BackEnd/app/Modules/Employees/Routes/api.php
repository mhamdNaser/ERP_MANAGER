<?php

use App\Modules\Employees\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::middleware('cnd.auth')->group(function () {
    Route::get('employees', [EmployeeController::class, 'index'])->middleware('permission:employees.view');
    Route::get('profile/details', [EmployeeController::class, 'profile']);
    Route::put('profile/details', [EmployeeController::class, 'updateProfile']);
    Route::post('profile/signature', [EmployeeController::class, 'updateSignature']);
    Route::post('employees', [EmployeeController::class, 'store'])->middleware('permission:employees.create');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->middleware('permission:employees.update');
    Route::put('employees/{employee}/password', [EmployeeController::class, 'updatePassword'])->middleware('permission:employees.update');
    Route::put('employees/{employee}/permissions', [EmployeeController::class, 'updatePermissions'])->middleware('permission:roles.permissions.manage');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:employees.delete');
});
