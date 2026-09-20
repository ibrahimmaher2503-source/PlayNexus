<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AuthenticatedSessionSecurity;
use App\Support\AuthenticationAudit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->tenant_id !== null) {
            abort(403);
        }

        $freshUser = $user
            ? User::query()->whereKey($user->getAuthIdentifier())->whereNull('tenant_id')->where('status', 'active')->first()
            : null;
        $admin = $freshUser?->platformAdmin()->where('is_active', true)->first();
        $sessionExpired = AuthenticatedSessionSecurity::expired($request, privileged: true);

        if (
            ! $freshUser
            || ! $admin
            || $request->session()->missing('platform_auth_version')
            || (int) $request->session()->get('platform_auth_version') !== (int) $freshUser->auth_version
            || $sessionExpired
        ) {
            if ($freshUser) {
                AuthenticationAudit::platform($request, $freshUser, 'auth.session_revoked', 'failure', $sessionExpired ? 'session_expired' : 'platform_access_or_auth_version_changed');
            }
            Auth::logout();
            $request->session()->forget('platform_auth_version');
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('locale', app()->getLocale());

            if ($request->expectsJson()) {
                abort(401);
            }

            return redirect()->route('platform.login')->with('status', __('account_security.expired'));
        }

        Auth::setUser($freshUser);
        $request->setUserResolver(fn (?string $guard = null) => $freshUser);

        return $next($request);
    }
}
