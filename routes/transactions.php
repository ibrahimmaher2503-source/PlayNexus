<?php

use App\Http\Controllers\TransactionHistoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['cache.headers:no_store;private', 'throttle:120,1'])->group(function (): void {
    Route::get('/app/transactions', [TransactionHistoryController::class, 'index'])->name('transactions.index');
});
