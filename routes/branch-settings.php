<?php

use App\Http\Controllers\BranchSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/app/branches/{branch}/settings', [BranchSettingsController::class, 'edit'])->name('branches.settings');
Route::patch('/app/branches/{branch}/settings', [BranchSettingsController::class, 'update'])->name('branches.settings.update');
