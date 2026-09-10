<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantContext::class)->current($request->user());

        if (! $tenant) {
            $request->session()->forget('branch_id');

            abort(404);
        }

        if ($branchId = $request->session()->get('branch_id')) {
            if (! $request->user()->activeBranches()->whereKey($branchId)->exists()) {
                $request->session()->forget('branch_id');
            }
        }

        return $next($request);
    }
}
