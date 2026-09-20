<?php

use App\Http\Controllers\AccountMfaController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\BranchContextController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\TenantReadController;
use App\Http\Controllers\TenantSetupController;
use App\Http\Controllers\TenantSubscriptionController;
use App\Http\Controllers\TenantSupportAccessHistoryController;
use App\Models\Branch;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');
Route::post('/locale', [LocaleController::class, 'store'])->name('locale.store');
require __DIR__.'/password.php';
require __DIR__.'/platform.php';

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

Route::middleware(['auth', 'tenant.access', 'security.audit-denials'])->group(function (): void {
    Route::get('/app/account/mfa', [AccountMfaController::class, 'show'])->name('account.mfa.show');
    Route::post('/app/account/mfa/enroll', [AccountMfaController::class, 'confirm'])->middleware('throttle:mfa')->name('account.mfa.confirm');
    Route::post('/app/account/mfa/verify', [AccountMfaController::class, 'challenge'])->middleware('throttle:mfa')->name('account.mfa.challenge');
});

Route::middleware(['auth', 'tenant.access', 'account.mfa', 'security.audit-denials'])->group(function (): void {
    require __DIR__.'/staff.php';
    require __DIR__.'/assignments.php';
    require __DIR__.'/branches-admin.php';
    require __DIR__.'/audit.php';
    require __DIR__.'/tenant-settings.php';
    require __DIR__.'/branch-settings.php';
    require __DIR__.'/roles.php';
    require __DIR__.'/families.php';
    require __DIR__.'/pricing.php';
    require __DIR__.'/tickets.php';
    require __DIR__.'/play-sessions.php';
    require __DIR__.'/play-session-adjustments.php';
    require __DIR__.'/pos.php';
    require __DIR__.'/discount-approvals.php';
    require __DIR__.'/refunds.php';
    require __DIR__.'/receipts.php';
    require __DIR__.'/transactions.php';
    require __DIR__.'/reports.php';
    require __DIR__.'/notifications.php';
    Route::get('/app', [BranchContextController::class, 'index'])->name('dashboard');
    Route::get('/app/tenant', [TenantReadController::class, 'show'])->name('tenant.show');
    Route::get('/app/setup', [TenantSetupController::class, 'index'])->name('tenant.setup');
    Route::get('/app/subscription', [TenantSubscriptionController::class, 'show'])->name('tenant.subscription.show');
    Route::get('/app/support-access', [TenantSupportAccessHistoryController::class, 'index'])->name('tenant.support-access.index');
    Route::post('/branch-context/{branch}', [BranchContextController::class, 'store'])->name('branch-context.store');

    Route::middleware('branch.access')->get('/branches/{branch}', fn (Branch $branch) => response()->json([
        'id' => $branch->id,
        'tenant_id' => $branch->tenant_id,
        'name' => $branch->name,
    ]));
});
