<?php

use App\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('/app/audit', [AuditLogController::class, 'index'])->name('audit.index');
Route::get('/app/audit/export', [AuditLogController::class, 'export'])->name('audit.export');
