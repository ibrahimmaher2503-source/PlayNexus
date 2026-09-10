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
        $branches = $request->user()->activeBranches()->orderBy('name')->get()
            ->filter(fn (Branch $branch): bool => Gate::forUser($request->user())->allows('view', $branch))
            ->values();

        return view('dashboard', [
            'tenant' => app(TenantContext::class)->current($request->user()),
            'branches' => $branches,
            'selectedBranch' => $branches->firstWhere('id', $request->session()->get('branch_id')),
        ]);
    }

    public function store(Request $request, Branch $branch): RedirectResponse
    {
        $branch = $request->user()->activeBranches()->whereKey($branch)->firstOrFail();
        Gate::forUser($request->user())->authorize('view', $branch);

        $request->session()->put('branch_id', $branch->id);

        return redirect()->route('dashboard');
    }
}
