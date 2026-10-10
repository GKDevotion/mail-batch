<?php

use App\Modules\ActivityLog\Http\Controllers\ActivityController;
use Illuminate\Support\Facades\Route;

Route::get('admin/activity', [ActivityController::class, 'index'])->middleware('can:activity.view')->name('admin.activity.index');
