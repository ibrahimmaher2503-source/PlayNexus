<?php

use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['cache.headers:no_store;private', 'throttle:120,1'])->group(function (): void {
    Route::get('/app/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('/app/ticket-types', [TicketController::class, 'storeType'])->name('ticket-types.store');
    Route::post('/app/tickets', [TicketController::class, 'issue'])->middleware('subscription.access:write')->name('tickets.issue');
    Route::post('/app/tickets/scan', [TicketController::class, 'scan'])->name('tickets.scan');
    Route::patch('/app/tickets/{ticket}/assignment', [TicketController::class, 'reassign'])->name('tickets.reassign');
    Route::post('/app/tickets/{ticket}/cancel', [TicketController::class, 'cancel'])->name('tickets.cancel');
    Route::post('/app/tickets/{ticket}/reprint', [TicketController::class, 'reprint'])->name('tickets.reprint');
});
