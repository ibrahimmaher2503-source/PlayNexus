<?php

use App\Http\Controllers\PricingRuleController;
use Illuminate\Support\Facades\Route;

Route::get('/app/pricing', [PricingRuleController::class, 'index'])->name('pricing.index');
Route::post('/app/pricing', [PricingRuleController::class, 'store'])->name('pricing.store');
Route::post('/app/pricing/{pricingRule}/versions', [PricingRuleController::class, 'storeVersion'])->name('pricing.versions.store');
