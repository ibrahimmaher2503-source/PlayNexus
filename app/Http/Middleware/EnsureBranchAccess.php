<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = app(TenantContext::class)->current($user);
        $branchId = $request->route('branch');
        $branchId = $branchId instanceof Branch ? $branchId->getKey() : $branchId;
        $branch = $tenant ? $user->accessibleBranches()->whereKey($branchId)->first() : null;

        abort_unless($tenant && $branch, 404);
        Gate::forUser($user)->authorize('view', $branch);

        $request->route()->setParameter('branch', $branch);

        return $next($request);
    }
}
