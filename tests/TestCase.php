<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

abstract class TestCase extends BaseTestCase
{
    private array $testMfaRecoveryCodes = [];

    protected function completeMfa(UserContract $user, string $password = 'password'): void
    {
        $user->refresh();
        $prefix = $user->tenant_id === null ? 'platform.mfa.' : 'account.mfa.';
        if (! $user->mfa_confirmed_at) {
            $this->get(route($prefix.'show'))->assertOk();
            $secret = Crypt::decryptString(session('pending_mfa_secret'));
            $code = (new Google2FA)->getCurrentOtp($secret);
            $this->post(route($prefix.'confirm'), ['current_password' => $password, 'code' => $code])->assertRedirect();
            $this->testMfaRecoveryCodes[$user->id] = json_decode(Crypt::decryptString(session('mfa_recovery_handoff')), true);
            $user->refresh();
        } else {
            $code = array_pop($this->testMfaRecoveryCodes[$user->id]);
            $this->post(route($prefix.'challenge'), ['code' => $code])->assertRedirect();
        }
    }

    public function actingAs(UserContract $user, $guard = null)
    {
        $this->withSession([
            'auth_version' => (int) ($user->auth_version ?? 1),
            'authenticated_at' => now('UTC')->timestamp,
            'last_authenticated_activity_at' => now('UTC')->timestamp,
        ]);

        return parent::actingAs($user, $guard);
    }
}
