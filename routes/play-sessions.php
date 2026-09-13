<?php

use App\Http\Controllers\PlaySessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['cache.headers:no_store;private', 'throttle:120,1'])->group(function (): void {
    Route::get('/app/sessions', [PlaySessionController::class, 'index'])->name('sessions.index');
    Route::post('/app/sessions/check-in', [PlaySessionController::class, 'checkIn'])->name('sessions.check-in');
});
