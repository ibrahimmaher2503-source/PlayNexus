<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = User::query()->find($request->user()->getAuthIdentifier());

        if (! $user || $user->status !== 'active') {
            $request->session()->forget('branch_id');
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401);
            }

            return redirect()->route('login');
        }

        $tenant = app(TenantContext::class)->current($user);

        if (! $tenant) {
            $request->session()->forget('branch_id');

            abort(404);
        }

        if ($branchId = $request->session()->get('branch_id')) {
            $branch = $user->activeBranches()->whereKey($branchId)->first();

            if (! $branch || ! Gate::forUser($user)->allows('view', $branch)) {
                $request->session()->forget('branch_id');
            }
        }

        return $next($request);
    }
}
