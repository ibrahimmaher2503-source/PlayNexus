<?php

use App\Http\Controllers\TenantSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/app/tenant/settings', [TenantSettingsController::class, 'edit'])->name('tenant.settings.edit');
Route::patch('/app/tenant/settings', [TenantSettingsController::class, 'update'])->name('tenant.settings.update');
