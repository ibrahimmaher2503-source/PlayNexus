<?php

use App\Http\Controllers\StaffStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/app/staff', [StaffStatusController::class, 'index'])->name('staff.index');
Route::get('/app/staff/create', [StaffStatusController::class, 'create'])->name('staff.create');
Route::post('/app/staff', [StaffStatusController::class, 'store'])->name('staff.store');
Route::patch('/app/staff/{user}/status', [StaffStatusController::class, 'update'])->name('staff.status');
