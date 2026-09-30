<?php

use App\Modules\Maintenance\Controllers\MaintenanceCatalogController;
use App\Modules\Maintenance\Controllers\MaintenanceItemController;
use App\Modules\Maintenance\Controllers\MaintenanceReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['cnd.auth', 'permission:maintenance.view'])->prefix('maintenance')->group(function () {
    Route::get('items', [MaintenanceItemController::class, 'index']);
    Route::get('items/{maintenanceItem}', [MaintenanceItemController::class, 'show']);
    Route::get('catalog', [MaintenanceCatalogController::class, 'index']);
    Route::get('statistics', [MaintenanceReportController::class, 'statistics']);
    Route::get('export', [MaintenanceReportController::class, 'export']);
    Route::get('imports', [MaintenanceReportController::class, 'imports']);
    Route::get('imports/{import}/download', [MaintenanceReportController::class, 'downloadImport']);

    Route::middleware('permission:maintenance.manage')->group(function () {
        Route::post('items', [MaintenanceItemController::class, 'store']);
        Route::put('items/{maintenanceItem}', [MaintenanceItemController::class, 'update']);
        Route::delete('items/{maintenanceItem}', [MaintenanceItemController::class, 'destroy']);
        Route::post('items/{maintenanceItem}/receive', [MaintenanceItemController::class, 'receive']);
        Route::post('items/{maintenanceItem}/move', [MaintenanceItemController::class, 'move']);
        Route::post('items/{maintenanceItem}/issue', [MaintenanceItemController::class, 'issue']);

        Route::post('categories', [MaintenanceCatalogController::class, 'storeCategory']);
        Route::put('categories/{category}', [MaintenanceCatalogController::class, 'updateCategory']);
        Route::delete('categories/{category}', [MaintenanceCatalogController::class, 'destroyCategory']);
        Route::post('types', [MaintenanceCatalogController::class, 'storeType']);
        Route::put('types/{type}', [MaintenanceCatalogController::class, 'updateType']);
        Route::delete('types/{type}', [MaintenanceCatalogController::class, 'destroyType']);
        Route::post('brands', [MaintenanceCatalogController::class, 'storeBrand']);
        Route::put('brands/{brand}', [MaintenanceCatalogController::class, 'updateBrand']);
        Route::delete('brands/{brand}', [MaintenanceCatalogController::class, 'destroyBrand']);

        Route::post('imports/preview', [MaintenanceReportController::class, 'previewImport']);
        Route::post('imports', [MaintenanceReportController::class, 'commitImport']);
        Route::delete('imports/{import}', [MaintenanceReportController::class, 'destroyImport']);
    });
});
