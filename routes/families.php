<?php

use App\Http\Controllers\FamilyController;
use Illuminate\Support\Facades\Route;

Route::get('/app/families', [FamilyController::class, 'index'])->name('families.index');
Route::get('/app/families/create', [FamilyController::class, 'create'])->name('families.create');
Route::post('/app/families', [FamilyController::class, 'store'])->name('families.store');
