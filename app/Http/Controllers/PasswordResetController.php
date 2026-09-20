<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Support\AuthenticationAudit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $email = $request->input('email');
        $request->merge([
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
        ]);

        $validated = $request->validate(
            ['email' => ['required', 'string', 'email', 'max:255']],
            [
                'email.required' => __('passwords.validation.email_required'),
                'email.string' => __('passwords.validation.email_invalid'),
                'email.email' => __('passwords.validation.email_invalid'),
                'email.max' => __('passwords.validation.email_invalid'),
            ],
        );

        $eligible = User::query()
            ->where('email', $validated['email'])
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('is_active', true))
            ->first();

        if ($eligible) {
            Password::sendResetLink(['email' => $validated['email']]);
            AuthenticationAudit::tenant($request, $eligible, 'auth.password_reset_requested', 'success', 'eligible_request');
        } else {
            AuthenticationAudit::unresolved($request, 'auth.password_reset_requested_unresolved', $validated['email']);
        }

        return back()->with('status', __('passwords.sent'));
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $email = $request->input('email');
        $request->merge([
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
        ]);

        $validated = $request->validate(
            [
                'token' => ['required', 'string'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
            ],
            [
                'token.required' => __('passwords.validation.token_required'),
                'token.string' => __('passwords.validation.token_invalid'),
                'email.required' => __('passwords.validation.email_required'),
                'email.string' => __('passwords.validation.email_invalid'),
                'email.email' => __('passwords.validation.email_invalid'),
                'email.max' => __('passwords.validation.email_invalid'),
                'password.required' => __('passwords.validation.password_required'),
                'password.string' => __('passwords.validation.password_invalid'),
                'password.min' => __('passwords.validation.password_invalid'),
                'password.max' => __('passwords.validation.password_invalid'),
                'password.confirmed' => __('passwords.validation.password_confirmation'),
            ],
        );

        $applied = false;
        $status = DB::transaction(function () use ($request, $validated, &$applied): string {
            return Password::reset($validated, function (User $user, string $password) use ($request, &$applied): void {
                $lockedUser = User::query()->lockForUpdate()->find($user->getKey());
                $lockedTenant = $lockedUser
                    ? Tenant::query()
                        ->whereKey($lockedUser->tenant_id)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first()
                    : null;

                if (! $lockedUser || ! $lockedTenant || $lockedUser->status !== 'active') {
                    return;
                }

                $lockedUser->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'auth_version' => (int) $lockedUser->auth_version + 1,
                ])->save();
                AuthenticationAudit::tenant($request, $lockedUser, 'auth.password_reset_completed', 'success', 'valid_single_use_token');
                $applied = true;
            });
        });

        if ($status === Password::PASSWORD_RESET && $applied) {
            return redirect()->route('login')->with('status', __('passwords.reset'));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __('passwords.invalid')]);
    }
}
