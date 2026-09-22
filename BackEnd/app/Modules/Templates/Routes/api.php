<?php

use App\Modules\Templates\Controllers\DocumentTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['cnd.auth', 'permission:templates.manage'])->group(function () {
    Route::get('document-templates', [DocumentTemplateController::class, 'index']);

    Route::get('document-templates/{key}/download', [DocumentTemplateController::class, 'download'])
        ->where('key', '[a-z_]+\.[a-z_]+');

    Route::get('document-templates/{key}/blank', [DocumentTemplateController::class, 'blank'])
        ->where('key', '[a-z_]+\.[a-z_]+');

    Route::post('document-templates/{key}', [DocumentTemplateController::class, 'upload'])
        ->middleware('throttle:20,1')
        ->where('key', '[a-z_]+\.[a-z_]+');

    Route::post('document-templates/{key}/restore', [DocumentTemplateController::class, 'restore'])
        ->middleware('throttle:20,1')
        ->where('key', '[a-z_]+\.[a-z_]+');
});
