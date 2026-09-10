<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            $branch = $request->user()->activeBranches()->whereKey($branchId)->first();

            if (! $branch || ! Gate::forUser($request->user())->allows('view', $branch)) {
                $request->session()->forget('branch_id');
            }
        }

        return $next($request);
    }
}
