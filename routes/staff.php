<?php

use App\Http\Controllers\StaffStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/app/staff', [StaffStatusController::class, 'index'])->name('staff.index');
Route::get('/app/staff/create', [StaffStatusController::class, 'create'])->name('staff.create');
Route::post('/app/staff', [StaffStatusController::class, 'store'])->middleware('subscription.access:write')->name('staff.store');
Route::get('/app/staff/{user}/edit', [StaffStatusController::class, 'edit'])->name('staff.edit');
Route::patch('/app/staff/{user}/identity', [StaffStatusController::class, 'updateIdentity'])->name('staff.identity');
Route::patch('/app/staff/{user}/status', [StaffStatusController::class, 'update'])->name('staff.status');
