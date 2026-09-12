<?php

use App\Http\Controllers\PlatformSessionController;
use App\Http\Controllers\PlatformTenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('platform')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [PlatformSessionController::class, 'create'])->name('platform.login');
        Route::post('/login', [PlatformSessionController::class, 'store'])->middleware('throttle:login')->name('platform.login.store');
    });

    Route::middleware('platform.access')->group(function (): void {
        Route::post('/logout', [PlatformSessionController::class, 'destroy'])->name('platform.logout');
        Route::get('/tenants', [PlatformTenantController::class, 'index'])->name('platform.tenants.index');
        Route::post('/tenants', [PlatformTenantController::class, 'store'])->name('platform.tenants.store');
        Route::patch('/tenants/{tenant}/status', [PlatformTenantController::class, 'updateStatus'])->name('platform.tenants.status');
    });
});
