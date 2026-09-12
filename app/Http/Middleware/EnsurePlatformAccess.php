<?php

namespace App\Http\Middleware;

use App\Models\User;
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

        if (
            ! $freshUser
            || ! $admin
            || $request->session()->missing('platform_auth_version')
            || (int) $request->session()->get('platform_auth_version') !== (int) $freshUser->auth_version
        ) {
            Auth::logout();
            $request->session()->forget('platform_auth_version');
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401);
            }

            return redirect()->route('platform.login');
        }

        Auth::setUser($freshUser);
        $request->setUserResolver(fn (?string $guard = null) => $freshUser);

        return $next($request);
    }
}
