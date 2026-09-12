<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
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
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => __('platform.auth.invalid_credentials')])
                ->onlyInput('email');
        }

        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->put('platform_auth_version', (int) $admin->auth_version);

        return redirect()->intended(route('platform.tenants.index', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->forget('platform_auth_version');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
