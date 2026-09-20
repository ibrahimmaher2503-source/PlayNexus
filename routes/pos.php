<?php

use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['cache.headers:no_store;private', 'throttle:120,1'])->group(function (): void {
    Route::get('/app/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/app/pos/products', [PosController::class, 'storeProduct'])->name('pos.products.store');
    Route::post('/app/pos/products/{product}/retire', [PosController::class, 'retireProduct'])->name('pos.products.retire');
    Route::post('/app/pos/quote', [PosController::class, 'quote'])->middleware('subscription.access:write')->name('pos.quote');
});

require __DIR__.'/ordinary-pos-orders.php';
