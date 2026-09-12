<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BranchContextController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $branches = $user->accessibleBranches()->orderBy('name')->get()
            ->filter(fn (Branch $branch): bool => Gate::forUser($user)->allows('view', $branch))
            ->values();

        return view('dashboard', [
            'tenant' => app(TenantContext::class)->current($user),
            'branches' => $branches,
            'selectedBranch' => $branches->firstWhere('id', $request->session()->get('branch_id')),
        ]);
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
