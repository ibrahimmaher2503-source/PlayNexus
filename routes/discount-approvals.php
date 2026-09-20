<?php

use App\Http\Controllers\DiscountApprovalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['cache.headers:no_store;private', 'throttle:120,1'])->group(function (): void {
    Route::post('/app/orders/{order}/discount-approvals', [DiscountApprovalController::class, 'request'])
        ->whereNumber('order')->name('discount-approvals.request');
    Route::post('/app/discount-approvals/{approval}/approve', [DiscountApprovalController::class, 'approve'])
        ->whereNumber('approval')->name('discount-approvals.approve');
    Route::post('/app/discount-approvals/{approval}/reject', [DiscountApprovalController::class, 'reject'])
        ->whereNumber('approval')->name('discount-approvals.reject');
    Route::post('/app/discount-approvals/{approval}/consume', [DiscountApprovalController::class, 'consume'])
        ->whereNumber('approval')->name('discount-approvals.consume');
});
