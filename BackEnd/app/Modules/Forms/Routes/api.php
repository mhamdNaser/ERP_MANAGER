<?php

use App\Modules\Forms\Controllers\CustomFormController;
use Illuminate\Support\Facades\Route;

Route::middleware('cnd.auth')->group(function () {
    Route::get('forms', [CustomFormController::class, 'index']);
    Route::post('forms', [CustomFormController::class, 'store'])->middleware('permission:forms.manage');
    Route::put('forms/{form}', [CustomFormController::class, 'update'])->middleware('permission:forms.manage');
    Route::delete('forms/{form}', [CustomFormController::class, 'destroy'])->middleware('permission:forms.manage');
    Route::post('forms/{form}/publish', [CustomFormController::class, 'publish']);
    Route::post('forms/publications/{publication}/submit', [CustomFormController::class, 'submit']);
    Route::get('forms/{form}/submissions', [CustomFormController::class, 'submissions']);
    Route::get('forms/users/{user}', [CustomFormController::class, 'userHistory']);
});
