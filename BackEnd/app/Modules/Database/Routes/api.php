<?php

use App\Modules\Database\Controllers\DatabaseBackupController;
use App\Modules\Database\Controllers\DatabaseExportController;
use App\Modules\Database\Controllers\DatabaseImportController;
use App\Modules\Database\Controllers\DatabaseMaintenanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('cnd.auth')->group(function () {
    Route::get('database-backups', [DatabaseBackupController::class, 'index'])->middleware('permission:database.backups.manage');
    Route::get('database-backups/tables', [DatabaseBackupController::class, 'tables'])->middleware('permission:database.backups.manage');
    Route::get('database-backups/presets', [DatabaseBackupController::class, 'presets'])->middleware('permission:database.backups.manage');
    Route::post('database-backups/migration-package', [DatabaseBackupController::class, 'migrationPackage'])->middleware(['permission:database.maintenance.manage', 'throttle:3,1']);
    Route::post('database-backups/internal', [DatabaseBackupController::class, 'internal'])->middleware('permission:database.backups.manage');
    Route::post('database-backups/external', [DatabaseBackupController::class, 'external'])->middleware('permission:database.backups.manage');
    Route::get('database-backups/{fileName}/download', [DatabaseBackupController::class, 'download'])->middleware('permission:database.backups.manage')->where('fileName', '[A-Za-z0-9._-]+');
    Route::get('database-backups/{fileName}/tables', [DatabaseBackupController::class, 'backupTables'])->middleware('permission:database.backups.manage')->where('fileName', '[A-Za-z0-9._-]+');
    Route::delete('database-backups/{fileName}', [DatabaseBackupController::class, 'destroy'])->middleware('permission:database.backups.manage')->where('fileName', '[A-Za-z0-9._-]+');

    Route::get('database-backups/export/{table}', [DatabaseExportController::class, 'export'])->middleware('permission:database.backups.manage')->where('table', '[A-Za-z0-9_]+');

    Route::get('database-import/tables', [DatabaseImportController::class, 'importableTables'])->middleware('permission:database.import.manage');
    Route::post('database-import/{table}/preview', [DatabaseImportController::class, 'preview'])->middleware(['permission:database.import.manage', 'throttle:10,1'])->where('table', '[A-Za-z0-9_]+');
    Route::post('database-import/{table}/commit', [DatabaseImportController::class, 'commit'])->middleware(['permission:database.import.manage', 'throttle:6,1'])->where('table', '[A-Za-z0-9_]+');

    Route::post('database-maintenance/truncate', [DatabaseMaintenanceController::class, 'truncate'])->middleware(['permission:database.maintenance.manage', 'throttle:6,1']);
    Route::post('database-maintenance/restore', [DatabaseMaintenanceController::class, 'restore'])->middleware(['permission:database.maintenance.manage', 'throttle:6,1']);
    Route::get('database-maintenance/logs', [DatabaseMaintenanceController::class, 'logs'])->middleware('permission:database.maintenance.manage');
});
