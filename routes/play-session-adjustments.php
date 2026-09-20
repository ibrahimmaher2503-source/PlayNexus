<?php

use App\Http\Controllers\PlaySessionAdjustmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['cache.headers:no_store;private', 'throttle:120,1'])->group(function (): void {
    Route::post('/app/sessions/{session}/adjustments', [PlaySessionAdjustmentController::class, 'store'])
        ->name('sessions.adjustments.store');
});
