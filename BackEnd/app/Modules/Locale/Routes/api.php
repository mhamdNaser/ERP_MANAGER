<?php
use App\Modules\Locale\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('locale/{lang}', [LocaleController::class, 'setLocale']);
Route::get('active-languages', [LocaleController::class, 'active']);
