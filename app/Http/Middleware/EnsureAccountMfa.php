<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $required = $user->tenant_id === null
            || $user->mfa_confirmed_at !== null
            || (config('account_security.owner_mfa_required') && DB::table('tenant_owners')->where('tenant_id', $user->tenant_id)->where('user_id', $user->id)->exists());

        if ($required && ($user->mfa_confirmed_at === null || (int) $request->session()->get('mfa_auth_version', 0) !== (int) $user->auth_version)) {
            if ($request->expectsJson()) {
                return response()->json(['code' => 'mfa_required', 'message' => __('account_security.required')], 403);
            }

            return redirect()->route($user->tenant_id === null ? 'platform.mfa.show' : 'account.mfa.show');
        }

        return $next($request);
    }
}
