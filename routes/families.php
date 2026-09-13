<?php

use App\Http\Controllers\FamilyController;
use App\Http\Controllers\FamilyProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/app/families', [FamilyController::class, 'index'])->name('families.index');
Route::get('/app/families/create', [FamilyController::class, 'create'])->name('families.create');
Route::post('/app/families', [FamilyController::class, 'store'])->name('families.store');
Route::get('/app/families/{guardian}', [FamilyProfileController::class, 'show'])->name('families.show');
Route::patch('/app/families/{guardian}', [FamilyProfileController::class, 'update'])->name('families.update');
Route::patch('/app/families/{guardian}/children/{child}', [FamilyProfileController::class, 'updateChild'])->name('families.children.update');
Route::post('/app/families/{guardian}/children', [FamilyProfileController::class, 'storeChild'])->name('families.children.store');
Route::patch('/app/families/{guardian}/children/{child}/consent', [FamilyProfileController::class, 'withdrawConsent'])->name('families.children.consent.withdraw');
Route::post('/app/families/{guardian}/children/{child}/relationships', [FamilyProfileController::class, 'storeRelationship'])->name('families.relationships.store');
Route::delete('/app/families/{guardian}/children/{child}/relationships/{relatedGuardian}', [FamilyProfileController::class, 'revokeRelationship'])->name('families.relationships.revoke');
