<?php

use App\Http\Controllers\PricingRuleController;
use Illuminate\Support\Facades\Route;

Route::get('/app/pricing', [PricingRuleController::class, 'index'])->name('pricing.index');
Route::post('/app/pricing', [PricingRuleController::class, 'store'])->name('pricing.store');
