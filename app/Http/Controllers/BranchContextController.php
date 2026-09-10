<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchContextController extends Controller
{
    public function index(Request $request)
    {
        $branches = $request->user()->activeBranches()->orderBy('name')->get();

        return view('dashboard', [
            'tenant' => app(TenantContext::class)->current($request->user()),
            'branches' => $branches,
            'selectedBranch' => $branches->firstWhere('id', $request->session()->get('branch_id')),
        ]);
    }

    public function store(Request $request, Branch $branch): RedirectResponse
    {
        $branch = $request->user()->activeBranches()->whereKey($branch)->firstOrFail();

        $request->session()->put('branch_id', $branch->id);

        return redirect()->route('dashboard');
    }
}
