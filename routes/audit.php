<?php

use App\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('/app/audit', [AuditLogController::class, 'index'])->name('audit.index');
