<?php

use App\Modules\Drive\Controllers\DriveController;
use Illuminate\Support\Facades\Route;

Route::get('public-files/{token}', [DriveController::class, 'publicDownload']);
Route::get('public-folders/{token}', [DriveController::class, 'publicFolder']);

Route::middleware('cnd.auth')->group(function () {
    Route::get('drive-files', [DriveController::class, 'index']);
    Route::post('drive-files', [DriveController::class, 'store']);
    Route::post('drive-archive', [DriveController::class, 'archive']);
    Route::get('drive-role-quotas', [DriveController::class, 'roleQuotasIndex']);
    Route::put('drive-role-quotas', [DriveController::class, 'updateRoleQuotas']);
    Route::post('drive-folders', [DriveController::class, 'storeFolder']);
    Route::post('drive-files/{file}/share', [DriveController::class, 'share']);
    Route::post('drive-folders/{folder}/share', [DriveController::class, 'shareFolder']);
    Route::delete('drive-files/{file}/share', [DriveController::class, 'revokeShare']);
    Route::delete('drive-folders/{folder}/share', [DriveController::class, 'revokeFolderShare']);
    Route::post('drive-files/{file}/public-link', [DriveController::class, 'publicLink']);
    Route::delete('drive-files/{file}/public-link', [DriveController::class, 'revokePublicLink']);
    Route::post('drive-folders/{folder}/public-link', [DriveController::class, 'publicFolderLink']);
    Route::delete('drive-folders/{folder}/public-link', [DriveController::class, 'revokePublicFolderLink']);
    Route::get('drive-files/{file}/download', [DriveController::class, 'download']);
    Route::get('drive-files/{file}/preview', [DriveController::class, 'preview']);
    Route::delete('drive-files/{file}', [DriveController::class, 'destroy']);
    Route::delete('drive-folders/{folder}', [DriveController::class, 'destroyFolder']);
});
