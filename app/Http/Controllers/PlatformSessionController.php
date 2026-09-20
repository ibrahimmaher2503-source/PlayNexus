<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\AuthenticatedSessionSecurity;
use App\Support\AuthenticationAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformSessionController extends Controller
{
    public function create()
    {
        return view('platform.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email' => Str::lower(trim($request->string('email')->toString())),
            'password' => $request->string('password')->toString(),
        ];
        $admin = User::query()
            ->where('email', $credentials['email'])
            ->whereNull('tenant_id')
            ->where('status', 'active')
            ->whereHas('platformAdmin', fn ($query) => $query->where('is_active', true))
            ->first();

        if (! $admin || ! Hash::check($credentials['password'], $admin->password)) {
            $admin
                ? AuthenticationAudit::platform($request, $admin, 'auth.login_failed', 'failure', 'credentials_rejected')
                : AuthenticationAudit::unresolved($request, 'platform.auth.login_failed', $credentials['email']);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('locale', app()->getLocale());

            return back()
                ->withErrors(['email' => __('platform.auth.invalid_credentials')])
                ->onlyInput('email');
        }

        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->put('platform_auth_version', (int) $admin->auth_version);
        AuthenticatedSessionSecurity::start($request);
        AuthenticationAudit::platform($request, $admin, 'auth.login_succeeded', 'success', 'credentials_accepted');

        return redirect()->intended(route('platform.dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuthenticationAudit::platform($request, $request->user(), 'auth.logout', 'success', 'user_logout');
        Auth::logout();
        $request->session()->forget('platform_auth_version');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
