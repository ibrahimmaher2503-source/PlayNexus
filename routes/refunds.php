<?php

use App\Http\Controllers\CashRefundController;
use Illuminate\Support\Facades\Route;

Route::middleware(['cache.headers:no_store;private', 'throttle:120,1'])->group(function (): void {
    Route::post('/app/orders/{order}/refunds', [CashRefundController::class, 'request'])->whereNumber('order')->name('refunds.request');
    Route::post('/app/refunds/{refund}/approve', [CashRefundController::class, 'approve'])->whereNumber('refund')->name('refunds.approve');
    Route::post('/app/refunds/{refund}/execute', [CashRefundController::class, 'execute'])->whereNumber('refund')->name('refunds.execute');
});
