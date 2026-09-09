<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantContext::class)->current();
        $branchId = $request->route('branch');
        $branchId = $branchId instanceof Branch ? $branchId->getKey() : $branchId;
        $branch = $tenant ? Branch::query()
            ->whereKey($branchId)
            ->where('is_active', true)
            ->where('tenant_id', $tenant->id)
            ->whereExists(fn ($query) => $query->from('branch_user')
                ->whereColumn('branch_user.branch_id', 'branches.id')
                ->where('branch_user.user_id', auth()->id())
                ->where('branch_user.is_active', true))
            ->first() : null;

        abort_unless($tenant && $branch, 404);

        $request->route()->setParameter('branch', $branch);

        return $next($request);
    }
}
