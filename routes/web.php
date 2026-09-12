<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\BranchContextController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\TenantReadController;
use App\Models\Branch;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');
Route::post('/locale', [LocaleController::class, 'store'])->name('locale.store');
require __DIR__.'/password.php';

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

Route::middleware(['auth', 'tenant.access'])->group(function (): void {
    require __DIR__.'/staff.php';
    require __DIR__.'/assignments.php';
    require __DIR__.'/branches-admin.php';
    require __DIR__.'/audit.php';
    require __DIR__.'/tenant-settings.php';
    require __DIR__.'/branch-settings.php';
    Route::get('/app', [BranchContextController::class, 'index'])->name('dashboard');
    Route::get('/app/tenant', [TenantReadController::class, 'show'])->name('tenant.show');
    Route::post('/branch-context/{branch}', [BranchContextController::class, 'store'])->name('branch-context.store');

    Route::middleware('branch.access')->get('/branches/{branch}', fn (Branch $branch) => response()->json([
        'id' => $branch->id,
        'tenant_id' => $branch->tenant_id,
        'name' => $branch->name,
    ]));
});
