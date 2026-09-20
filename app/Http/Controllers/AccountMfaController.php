<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuthenticationAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AccountMfaController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $enrolled = $user->mfa_confirmed_at !== null;
        $secret = null;
        if (! $enrolled) {
            if (! $request->session()->has('pending_mfa_secret')) {
                $request->session()->put('pending_mfa_secret', Crypt::encryptString((new Google2FA)->generateSecretKey()));
            }
            $secret = Crypt::decryptString($request->session()->get('pending_mfa_secret'));
        }
        $recovery = $request->session()->pull('mfa_recovery_handoff');
        $recoveryCodes = $recovery ? json_decode(Crypt::decryptString($recovery), true, flags: JSON_THROW_ON_ERROR) : [];

        return response()->view('auth.mfa', compact('user', 'enrolled', 'secret', 'recoveryCodes'))->header('Cache-Control', 'no-store, private');
    }

    public function confirm(Request $request)
    {
        $request->validate(['current_password' => ['required', 'string'], 'code' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);
        $pending = $request->session()->get('pending_mfa_secret');
        abort_unless(is_string($pending), 409);
        $secret = Crypt::decryptString($pending);
        if (! Hash::check($request->string('current_password')->toString(), $request->user()->password)) {
            $this->failure($request, $request->user());
            throw ValidationException::withMessages(['current_password' => __('account_security.invalid')]);
        }
        if ((new Google2FA)->verifyKeyNewer($secret, $request->string('code')->toString(), 0, 1) === false) {
            $this->failure($request, $request->user());
            throw ValidationException::withMessages(['code' => __('account_security.invalid')]);
        }
        $codes = [];
        DB::transaction(function () use ($request, $secret, &$codes): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($user->status === 'active' && (int) $user->auth_version === (int) $request->user()->auth_version, 403);
            abort_if($user->mfa_confirmed_at !== null, 409);
            if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
                throw ValidationException::withMessages(['current_password' => __('account_security.invalid')]);
            }
            $counter = (new Google2FA)->verifyKeyNewer($secret, $request->string('code')->toString(), 0, 1);
            if ($counter === false) {
                throw ValidationException::withMessages(['code' => __('account_security.invalid')]);
            }
            $codes = array_map(fn () => Str::random(20), range(1, 8));
            $user->forceFill([
                'mfa_secret' => $secret,
                'mfa_confirmed_at' => now('UTC'),
                'mfa_last_counter' => $counter,
                'mfa_recovery_codes' => array_map(fn ($code) => Hash::make($code), $codes),
                'auth_version' => (int) $user->auth_version + 1,
            ])->save();
            $this->audit($request, $user, 'auth.mfa_enrolled');
            $this->verifySession($request, $user);
        });
        $request->session()->forget('pending_mfa_secret');
        $request->session()->put('mfa_recovery_handoff', Crypt::encryptString(json_encode($codes, JSON_THROW_ON_ERROR)));

        return back()->with('status', __('account_security.enrolled'));
    }

    public function challenge(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:64']]);
        $actor = $request->user();
        $accepted = DB::transaction(function () use ($request, $actor): bool {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless((int) $user->auth_version === (int) $actor->auth_version, 403);
            abort_unless($user->status === 'active' && $user->mfa_confirmed_at && $user->mfa_secret, 403);
            $code = $request->string('code')->toString();
            $counter = preg_match('/^[0-9]{6}$/', $code)
                ? (new Google2FA)->verifyKeyNewer($user->mfa_secret, $code, $user->mfa_last_counter, 1)
                : false;
            $codes = $user->mfa_recovery_codes ?? [];
            $recoveryIndex = null;
            if ($counter === false) {
                foreach ($codes as $index => $hash) {
                    if (Hash::check($code, $hash)) {
                        $recoveryIndex = $index;
                        break;
                    }
                }
                if ($recoveryIndex === null) {
                    return false;
                }
                unset($codes[$recoveryIndex]);
                $user->mfa_recovery_codes = array_values($codes);
            } else {
                $user->mfa_last_counter = $counter;
            }
            $user->save();
            $this->audit($request, $user, $recoveryIndex === null ? 'auth.mfa_verified' : 'auth.mfa_recovery_used');
            $this->verifySession($request, $user);

            return true;
        });
        if (! $accepted) {
            $this->failure($request, $actor);
            throw ValidationException::withMessages(['code' => __('account_security.invalid')]);
        }

        return to_route($actor->tenant_id === null ? 'platform.dashboard' : 'dashboard');
    }

    private function verifySession(Request $request, User $user): void
    {
        $request->session()->regenerate();
        $request->session()->put('mfa_auth_version', (int) $user->auth_version);
        $request->session()->put($user->tenant_id === null ? 'platform_auth_version' : 'auth_version', (int) $user->auth_version);
    }

    private function audit(Request $request, User $user, string $action): void
    {
        $user->tenant_id === null
            ? AuthenticationAudit::platform($request, $user, $action, 'success', 'account_security')
            : AuthenticationAudit::tenant($request, $user, $action, 'success', 'account_security');
    }

    private function failure(Request $request, User $user): void
    {
        $user->tenant_id === null
            ? AuthenticationAudit::platform($request, $user, 'auth.mfa_failed', 'failure', 'invalid_factor')
            : AuthenticationAudit::tenant($request, $user, 'auth.mfa_failed', 'failure', 'invalid_factor');
    }
}
