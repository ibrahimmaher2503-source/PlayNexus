<?php

use App\Http\Controllers\OperationalNotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/app/notifications', [OperationalNotificationController::class, 'index'])->name('notifications.index');
