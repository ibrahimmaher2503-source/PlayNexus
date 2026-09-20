<?php

use App\Http\Controllers\BranchAssignmentController;
use Illuminate\Support\Facades\Route;

Route::get('/app/assignments', [BranchAssignmentController::class, 'index'])->name('assignments.index');
Route::put('/app/assignments/{user}/{branch}', [BranchAssignmentController::class, 'update'])->name('assignments.update');
