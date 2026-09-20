<?php

use App\Http\Controllers\OrdinaryPosOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['cache.headers:no_store;private', 'throttle:120,1'])->group(function (): void {
    Route::post('/app/pos/orders', [OrdinaryPosOrderController::class, 'store'])->middleware('subscription.access:write')->name('pos.orders.store');
    Route::post('/app/pos/orders/{order}/payments', [OrdinaryPosOrderController::class, 'pay'])->whereNumber('order')->middleware('subscription.access:write')->name('pos.orders.payments.store');
});
