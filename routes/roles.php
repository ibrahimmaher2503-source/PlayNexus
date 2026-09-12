<?php

use App\Http\Controllers\RoleManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/app/roles', [RoleManagementController::class, 'index'])->name('roles.index');
Route::post('/app/roles', [RoleManagementController::class, 'store'])->name('roles.store');
Route::patch('/app/roles/{role}', [RoleManagementController::class, 'update'])->name('roles.update');
