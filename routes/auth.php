<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:30,1')->name('login.store');

    // Self-registration is OFF by default (MAILBATCH_REGISTRATION_ENABLED=false).
    if (config('mailbatch.registration_enabled')) {
        Route::get('/register', [RegisterController::class, 'create'])->name('register');
        Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
    }
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
