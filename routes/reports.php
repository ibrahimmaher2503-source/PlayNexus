<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/app/reports/{type?}', [ReportController::class, 'index'])->middleware('subscription.access:historical_reports')->name('reports.index');
Route::get('/app/operations-report', fn () => redirect()->route('reports.index', ['type' => 'attendance']))->middleware('subscription.access:historical_reports')->name('reports.operations');
Route::get('/app/reports/{type}/export', [ReportController::class, 'export'])->middleware('subscription.access:historical_reports')->name('reports.export');
