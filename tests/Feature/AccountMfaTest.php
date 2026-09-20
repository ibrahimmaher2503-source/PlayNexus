<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AccountMfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_requires_enrollment_and_a_verified_factor_on_every_new_login(): void
    {
        $admin = $this->admin();
        $this->post(route('platform.login.store'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect();
        $this->get(route('platform.tenants.index'))->assertRedirect(route('platform.mfa.show'));
        $this->postJson(route('platform.tenants.store'), [])->assertForbidden();
        $this->completeMfa($admin);
        $this->get(route('platform.tenants.index'))->assertOk();
        $raw = DB::table('users')->where('id', $admin->id)->first();
        $this->assertNotSame($admin->mfa_secret, $raw->mfa_secret);
        $this->assertArrayNotHasKey('mfa_secret', $admin->toArray());
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'auth.mfa_enrolled', 'actor_user_id' => $admin->id]);
        $this->post(route('platform.logout'))->assertRedirect();
        $this->post(route('platform.login.store'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect();
        $this->get(route('platform.tenants.index'))->assertRedirect(route('platform.mfa.show'));
        $this->completeMfa($admin);
        $this->get(route('platform.tenants.index'))->assertOk();
    }

    public function test_enrollment_requires_password_and_code_and_recovery_is_single_use(): void
    {
        $admin = $this->admin();
        $this->post(route('platform.login.store'), ['email' => $admin->email, 'password' => 'password']);
        $this->get(route('platform.mfa.show'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $secret = Crypt::decryptString(session('pending_mfa_secret'));
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post(route('platform.mfa.confirm'), ['current_password' => 'wrong', 'code' => $code])->assertSessionHasErrors('current_password');
        $this->assertNull($admin->fresh()->mfa_confirmed_at);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'auth.mfa_failed']);
        $this->post(route('platform.mfa.confirm'), ['current_password' => 'password', 'code' => $code])->assertRedirect();
        $codes = json_decode(Crypt::decryptString(session('mfa_recovery_handoff')), true);
        $this->withSession(['mfa_auth_version' => null])->post(route('platform.mfa.challenge'), ['code' => $code])->assertSessionHasErrors('code');
        $this->post(route('platform.mfa.challenge'), ['code' => $codes[0]])->assertRedirect(route('platform.dashboard'));
        $this->withSession(['mfa_auth_version' => null])->post(route('platform.mfa.challenge'), ['code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertCount(7, $admin->fresh()->mfa_recovery_codes);
    }

    public function test_owner_enforcement_is_configurable_and_never_grants_platform_access(): void
    {
        config(['account_security.owner_mfa_required' => true]);
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'password' => Hash::make('password')]);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('account.mfa.show'));
        $this->completeMfa($owner);
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('platform.tenants.index'))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.mfa_enrolled', 'tenant_id' => $tenant->id]);
    }

    public function test_mfa_throttle_isolated_by_authenticated_account_on_the_same_ip(): void
    {
        $sharedIp = '203.0.113.44';
        $firstTenant = Tenant::factory()->create();
        $firstUser = User::factory()->create(['tenant_id' => $firstTenant->id, 'password' => Hash::make('password')]);
        $secondTenant = Tenant::factory()->create();
        $secondUser = User::factory()->create(['tenant_id' => $secondTenant->id, 'password' => Hash::make('password')]);
        $platformUser = $this->admin();

        $this->withServerVariables(['REMOTE_ADDR' => $sharedIp])->actingAs($firstUser)
            ->get(route('account.mfa.show'))->assertOk();

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('account.mfa.confirm'), [
                'current_password' => 'wrong-password',
                'code' => '000000',
            ])->assertUnprocessable();
        }

        $this->postJson(route('account.mfa.confirm'), [
            'current_password' => 'wrong-password',
            'code' => '000000',
        ])->assertTooManyRequests();
        $this->assertNull($firstUser->fresh()->mfa_confirmed_at);

        $this->actingAs($secondUser)->get(route('account.mfa.show'))->assertOk();
        $this->postJson(route('account.mfa.confirm'), [
            'current_password' => 'wrong-password',
            'code' => '000000',
        ])->assertUnprocessable();
        $this->assertNull($secondUser->fresh()->mfa_confirmed_at);

        $this->post(route('logout'))->assertRedirect();
        $this->post(route('platform.login.store'), [
            'email' => $platformUser->email,
            'password' => 'password',
        ])->assertRedirect();
        $this->get(route('platform.mfa.show'))->assertOk();
        $this->postJson(route('platform.mfa.confirm'), [
            'current_password' => 'wrong-password',
            'code' => '000000',
        ])->assertUnprocessable();
        $this->assertNull($platformUser->fresh()->mfa_confirmed_at);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['tenant_id' => null, 'password' => Hash::make('password')]);
        DB::table('platform_admins')->insert(['user_id' => $admin->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $admin;
    }
}
