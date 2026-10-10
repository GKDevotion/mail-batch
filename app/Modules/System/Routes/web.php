<?php

use App\Modules\System\Http\Controllers\ModuleController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/modules')->name('admin.modules.')->middleware('can:system.manage')->group(function () {
    Route::get('/', [ModuleController::class, 'index'])->name('index');
    Route::post('/migrate', [ModuleController::class, 'migrate'])->middleware('throttle:5,1')->name('migrate');
    Route::post('/{key}/toggle', [ModuleController::class, 'toggle'])->where('key', '[a-z][a-z0-9_]*')->name('toggle');
    Route::get('/{key}/settings', [ModuleController::class, 'settings'])->where('key', '[a-z][a-z0-9_]*')->name('settings');
    Route::put('/{key}/settings', [ModuleController::class, 'updateSettings'])->where('key', '[a-z][a-z0-9_]*')->name('settings.update');
});
