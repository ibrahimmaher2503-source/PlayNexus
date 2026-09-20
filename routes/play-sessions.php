<?php

use App\Http\Controllers\CashSettlementController;
use App\Http\Controllers\PlaySessionController;
use App\Http\Controllers\SessionLifecycleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['cache.headers:no_store;private', 'throttle:120,1'])->group(function (): void {
    Route::get('/app/sessions', [PlaySessionController::class, 'index'])->name('sessions.index');
    Route::post('/app/sessions/check-in', [PlaySessionController::class, 'checkIn'])->middleware('subscription.access:write')->name('sessions.check-in');
    Route::post('/app/sessions/{session}/checkout', [PlaySessionController::class, 'prepareCheckout'])->name('sessions.checkout.prepare');
    Route::post('/app/sessions/{session}/settle-cash', [CashSettlementController::class, 'store'])->middleware('subscription.access:write')->name('sessions.settle-cash');
    Route::get('/app/sessions/{session}/state', [SessionLifecycleController::class, 'state'])->name('sessions.state');
    Route::get('/app/sessions/{session}', [SessionLifecycleController::class, 'state'])->name('sessions.show');
    Route::post('/app/sessions/{session}/extend', [SessionLifecycleController::class, 'extend'])->name('sessions.extend');
    Route::post('/app/sessions/{session}/cancel', [SessionLifecycleController::class, 'cancel'])->name('sessions.cancel');
});
