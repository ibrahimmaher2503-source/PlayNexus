<?php

use App\Http\Controllers\StaffStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/app/staff', [StaffStatusController::class, 'index'])->name('staff.index');
Route::patch('/app/staff/{user}/status', [StaffStatusController::class, 'update'])->name('staff.status');
