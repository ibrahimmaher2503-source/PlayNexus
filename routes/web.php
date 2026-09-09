<?php

use App\Models\Branch;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'branch.access'])->get('/branches/{branch}', fn (Branch $branch) => response()->json([
    'id' => $branch->id,
    'tenant_id' => $branch->tenant_id,
    'name' => $branch->name,
]));
