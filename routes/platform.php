<?php

use App\Http\Controllers\AccountMfaController;
use App\Http\Controllers\PlatformDashboardController;
use App\Http\Controllers\PlatformPlanController;
use App\Http\Controllers\PlatformSessionController;
use App\Http\Controllers\PlatformSubscriptionController;
use App\Http\Controllers\PlatformSupportAccessController;
use App\Http\Controllers\PlatformTenantController;
use App\Http\Controllers\TenantOwnerInvitationController;
use Illuminate\Support\Facades\Route;

Route::prefix('platform')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [PlatformSessionController::class, 'create'])->name('platform.login');
        Route::post('/login', [PlatformSessionController::class, 'store'])->middleware('throttle:login')->name('platform.login.store');
    });

    Route::middleware(['platform.access', 'security.audit-denials'])->group(function (): void {
        Route::post('/logout', [PlatformSessionController::class, 'destroy'])->name('platform.logout');
        Route::get('/account/mfa', [AccountMfaController::class, 'show'])->name('platform.mfa.show');
        Route::post('/account/mfa/enroll', [AccountMfaController::class, 'confirm'])->middleware('throttle:mfa')->name('platform.mfa.confirm');
        Route::post('/account/mfa/verify', [AccountMfaController::class, 'challenge'])->middleware('throttle:mfa')->name('platform.mfa.challenge');
        Route::middleware('account.mfa')->group(function (): void {
            Route::get('/', PlatformDashboardController::class)->name('platform.dashboard');
            Route::get('/tenants', [PlatformTenantController::class, 'index'])->name('platform.tenants.index');
            Route::post('/tenants', [PlatformTenantController::class, 'store'])->name('platform.tenants.store');
            Route::get('/tenants/{tenant}', [PlatformTenantController::class, 'show'])->name('platform.tenants.show');
            Route::post('/tenants/{tenant}/owner-invitation/reissue', [PlatformTenantController::class, 'reissueOwnerInvitation'])->name('platform.tenants.owner-invitation.reissue');
            Route::patch('/tenants/{tenant}/status', [PlatformTenantController::class, 'updateStatus'])->name('platform.tenants.status');
            Route::get('/support-access', [PlatformSupportAccessController::class, 'index'])->name('platform.support-access.index');
            Route::post('/support-access', [PlatformSupportAccessController::class, 'store'])->name('platform.support-access.store');
            Route::get('/support-access/{supportAccessGrant}', [PlatformSupportAccessController::class, 'show'])->name('platform.support-access.show');
            Route::patch('/support-access/{supportAccessGrant}/revoke', [PlatformSupportAccessController::class, 'revoke'])->name('platform.support-access.revoke');
            Route::get('/plans', [PlatformPlanController::class, 'index'])->name('platform.plans.index');
            Route::post('/plans', [PlatformPlanController::class, 'store'])->name('platform.plans.store');
            Route::patch('/plans/{plan}', [PlatformPlanController::class, 'update'])->name('platform.plans.update');
            Route::patch('/plans/{plan}/status', [PlatformPlanController::class, 'updateStatus'])->name('platform.plans.status');
            Route::get('/subscriptions', [PlatformSubscriptionController::class, 'index'])->name('platform.subscriptions.index');
            Route::patch('/tenants/{tenant}/subscription', [PlatformSubscriptionController::class, 'assign'])->name('platform.subscriptions.assign');
            Route::patch('/subscriptions/{subscription}/status', [PlatformSubscriptionController::class, 'updateStatus'])->name('platform.subscriptions.status');
            Route::post('/subscriptions/{subscription}/trial', [PlatformSubscriptionController::class, 'extendTrial'])->name('platform.subscriptions.trial');
            Route::patch('/subscriptions/{subscription}/limits', [PlatformSubscriptionController::class, 'updateLimits'])->name('platform.subscriptions.limits');
            Route::post('/subscriptions/{subscription}/invoices', [PlatformSubscriptionController::class, 'storeInvoice'])->name('platform.subscriptions.invoices.store');
        });
    });
});

Route::middleware('guest')->group(function (): void {
    Route::get('/owner-invitations/{token}', [TenantOwnerInvitationController::class, 'show'])
        ->where('token', '[A-Fa-f0-9]{64}')
        ->name('owner-invitations.show');
    Route::post('/owner-invitations/{token}', [TenantOwnerInvitationController::class, 'accept'])
        ->where('token', '[A-Fa-f0-9]{64}')
        ->middleware('throttle:login')
        ->name('owner-invitations.accept');
});
