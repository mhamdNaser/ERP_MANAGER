<?php

use App\Modules\Tasks\Controllers\TaskController;
use App\Modules\Tasks\Controllers\TaskStatisticsController;
use Illuminate\Support\Facades\Route;

Route::middleware('cnd.auth')->group(function () {
    Route::get('tasks', [TaskController::class, 'index']);
    Route::get('task-activities', [TaskController::class, 'activities']);
    Route::get('task-statistics', [TaskStatisticsController::class, 'index']);
    Route::post('tasks', [TaskController::class, 'store']);
    Route::put('tasks/{task}', [TaskController::class, 'update']);
    Route::patch('tasks/{task}/move', [TaskController::class, 'move']);
    Route::delete('tasks/{task}', [TaskController::class, 'destroy']);
});
