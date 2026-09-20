<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuthenticatedSessionSecurity;
use App\Support\AuthenticationAudit;
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
        $sessionExpired = AuthenticatedSessionSecurity::expired($request);

        if (
            ! $user
            || $user->status !== 'active'
            || $request->session()->missing('auth_version')
            || (int) $request->session()->get('auth_version') !== (int) $user->auth_version
            || $sessionExpired
        ) {
            if ($user?->tenant_id !== null) {
                AuthenticationAudit::tenant($request, $user, 'auth.session_revoked', 'failure', $sessionExpired ? 'session_expired' : 'user_status_or_auth_version_changed');
            }
            $request->session()->forget('branch_id');
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('locale', app()->getLocale());

            if ($request->expectsJson()) {
                abort(401);
            }

            return redirect()->route('login')->with('status', __('account_security.expired'));
        }

        Auth::setUser($user);
        $request->setUserResolver(fn (?string $guard = null) => $user);

        $tenant = app(TenantContext::class)->current($user);

        if (! $tenant) {
            // A tenant suspension may occur after this user established the
            // session. Treat that state change as a revocation, rather than
            // revealing a misleading not-found response from a protected
            // route. A genuinely missing tenant is still a fail-closed 404.
            if ($user->tenant()->exists()) {
                AuthenticationAudit::tenant($request, $user, 'auth.session_revoked', 'failure', 'tenant_inactive_or_suspended');
                $request->session()->forget('branch_id');
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $request->session()->put('locale', app()->getLocale());

                if ($request->expectsJson()) {
                    abort(401);
                }

                return redirect()->route('login')->with('status', __('account_security.expired'));
            }
            $request->session()->forget('branch_id');

            abort(404);
        }

        if ($branchId = $request->session()->get('branch_id')) {
            $branch = $user->accessibleBranches()->whereKey($branchId)->first();

            if (! $branch || ! Gate::forUser($user)->allows('view', $branch)) {
                $request->session()->forget('branch_id');
            }
        }

        return $next($request);
    }
}
