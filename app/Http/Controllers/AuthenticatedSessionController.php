<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuthenticatedSessionSecurity;
use App\Support\AuthenticationAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email' => Str::lower(trim($request->string('email')->toString())),
            'password' => $request->string('password')->toString(),
        ];
        $candidate = User::query()->where('email', $credentials['email'])->whereNotNull('tenant_id')->first();

        if (! Auth::attempt($credentials) || $request->user()->status !== 'active' || ! app(TenantContext::class)->current($request->user())) {
            $candidate
                ? AuthenticationAudit::tenant($request, $candidate, 'auth.login_failed', 'failure', 'credentials_or_access_rejected')
                : AuthenticationAudit::unresolved($request, 'auth.login_failed', $credentials['email']);
            Auth::logout();
            $request->session()->forget('branch_id');
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('locale', app()->getLocale());

            return back()
                ->withErrors(['email' => __('These credentials do not match our records.')])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->session()->put('auth_version', (int) $request->user()->auth_version);
        AuthenticatedSessionSecurity::start($request);
        AuthenticationAudit::tenant($request, $request->user(), 'auth.login_succeeded', 'success', 'credentials_accepted');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuthenticationAudit::tenant($request, $request->user(), 'auth.logout', 'success', 'user_logout');
        $request->session()->forget('branch_id');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
