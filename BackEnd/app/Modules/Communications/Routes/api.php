<?php

use App\Modules\Communications\Controllers\CommunicationController;
use App\Modules\Communications\Controllers\FormalCorrespondenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('cnd.auth')->group(function () {
    Route::get('communication-directory', [CommunicationController::class, 'directory'])->middleware('permission:messages.create|correspondences.create');
    Route::get('messages', [CommunicationController::class, 'messages'])->middleware('permission:messages.view|correspondences.view');
    Route::get('messages/unread-count', [CommunicationController::class, 'unreadMessagesCount'])->middleware('permission:messages.view|correspondences.view');
    Route::post('messages', [CommunicationController::class, 'storeMessage'])->middleware('permission:messages.create|correspondences.create');
    Route::patch('messages/{message}/read', [CommunicationController::class, 'markMessageRead'])->middleware('permission:messages.view|correspondences.view');
    Route::post('messages/{message}/replies', [CommunicationController::class, 'reply'])->middleware('permission:messages.reply|correspondences.reply');
    Route::delete('messages/{message}', [CommunicationController::class, 'deleteMessage'])->middleware('permission:messages.view|correspondences.view');
    Route::get('formal-correspondences', [FormalCorrespondenceController::class, 'index'])->middleware('permission:formal_correspondences.view');
    Route::get('formal-correspondences-directory', [FormalCorrespondenceController::class, 'directory'])->middleware('permission:formal_correspondences.create|formal_correspondences.route');
    Route::post('formal-correspondences', [FormalCorrespondenceController::class, 'store'])->middleware('permission:formal_correspondences.create');
    Route::post('formal-correspondences/documents/{document}', [FormalCorrespondenceController::class, 'updateDocument'])->middleware('permission:formal_correspondences.route');
    Route::put('formal-correspondences/events/{event}', [FormalCorrespondenceController::class, 'updateEvent'])->middleware('permission:formal_correspondences.route');
    Route::post('formal-correspondences/events/{event}/decision', [FormalCorrespondenceController::class, 'decide'])->middleware('permission:formal_correspondences.route');
    Route::post('formal-correspondences/events/{event}/response', [FormalCorrespondenceController::class, 'respond'])->middleware('permission:formal_correspondences.route');
    Route::post('formal-correspondences/events/{event}/assign', [FormalCorrespondenceController::class, 'assign'])->middleware('permission:formal_correspondences.route');
    Route::post('formal-correspondences/events/{event}/documents', [FormalCorrespondenceController::class, 'addEventDocument'])->middleware('permission:formal_correspondences.route');
    Route::delete('formal-correspondences/events/{event}', [FormalCorrespondenceController::class, 'destroyEvent'])->middleware('permission:formal_correspondences.route');
    Route::post('formal-correspondences/{formalCorrespondence}', [FormalCorrespondenceController::class, 'update'])->middleware('permission:formal_correspondences.create');
    Route::delete('formal-correspondences/{formalCorrespondence}', [FormalCorrespondenceController::class, 'destroy'])->middleware('permission:formal_correspondences.view');
    Route::post('formal-correspondences/{formalCorrespondence}/events', [FormalCorrespondenceController::class, 'addEvent'])->middleware('permission:formal_correspondences.route');
    Route::get('circulars', [CommunicationController::class, 'circulars'])->middleware('permission:circulars.view');
    Route::get('circulars/{circular}', [CommunicationController::class, 'showCircular'])->middleware('permission:circulars.view');
    Route::post('circulars', [CommunicationController::class, 'storeCircular'])->middleware('permission:circulars.create');
});
