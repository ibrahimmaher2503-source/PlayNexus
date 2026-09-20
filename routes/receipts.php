<?php

use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::middleware(['cache.headers:no_store;private', 'throttle:120,1'])->group(function (): void {
    Route::get('/app/orders/{order}/receipt', [ReceiptController::class, 'show'])
        ->whereNumber('order')->name('receipts.show');
    Route::post('/app/orders/{order}/receipt/reprint', [ReceiptController::class, 'reprint'])
        ->whereNumber('order')->name('receipts.reprint');
});
