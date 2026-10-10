<?php

use App\Modules\Access\Http\Controllers\RoleController;
use App\Modules\Access\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/access')->name('admin.access.')->middleware('can:access.manage')->group(function () {
    Route::get('/roles', [RoleController::class, 'index'])->name('roles');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    Route::get('/users', [UserRoleController::class, 'index'])->name('users');
    Route::put('/users/{user}', [UserRoleController::class, 'update'])->name('users.update');
});
