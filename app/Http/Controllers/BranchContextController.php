<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\ActorDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BranchContextController extends Controller
{
    public function index(Request $request, ActorDashboard $dashboard)
    {
        return $dashboard->show($request);
    }

    public function store(Request $request, Branch $branch): RedirectResponse
    {
        $user = $request->user();
        $branch = $user->accessibleBranches()->whereKey($branch->getKey())->firstOrFail();
        Gate::forUser($user)->authorize('view', $branch);

        $request->session()->put('branch_id', $branch->id);

        return redirect()->route('dashboard');
    }
}
