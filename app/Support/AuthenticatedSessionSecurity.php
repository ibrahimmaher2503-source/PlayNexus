<?php

namespace App\Support;

use Illuminate\Http\Request;

class AuthenticatedSessionSecurity
{
    public static function start(Request $request): void
    {
        $request->session()->put([
            'authenticated_at' => now('UTC')->timestamp,
            'last_authenticated_activity_at' => now('UTC')->timestamp,
        ]);
    }

    public static function expired(Request $request, bool $privileged = false): bool
    {
        $started = $request->session()->get('authenticated_at');
        $last = $request->session()->get('last_authenticated_activity_at');
        $now = now('UTC')->timestamp;
        $idle = (int) config('account_security.'.($privileged ? 'privileged_idle_minutes' : 'staff_idle_minutes')) * 60;
        $absolute = config('account_security.absolute_minutes');

        if (! is_int($started) || ! is_int($last) || $started > $now || $last > $now || $last < $started || $idle <= 0 || $now - $last >= $idle) {
            return true;
        }

        // Fail closed for malformed configured policy; null means not yet approved.
        if ($absolute !== null && (! is_numeric($absolute) || (int) $absolute <= 0 || $now - $started >= (int) $absolute * 60)) {
            return true;
        }

        $request->session()->put('last_authenticated_activity_at', $now);

        return false;
    }
}
