<?php

use App\Http\Controllers\BranchAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/app/branches/manage', [BranchAdminController::class, 'index'])->name('branches.manage');
Route::post('/app/branches', [BranchAdminController::class, 'store'])->name('branches.store');
Route::patch('/app/branches/{branch}/status', [BranchAdminController::class, 'updateStatus'])->name('branches.status');
