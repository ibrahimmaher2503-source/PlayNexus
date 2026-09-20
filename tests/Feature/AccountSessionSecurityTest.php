<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_idle_boundary_revokes_an_established_session(): void
    {
        $user = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->travel(29)->minutes();
        $this->get(route('dashboard'))->assertOk();
        $this->travel(30)->minutes();
        $this->getJson(route('dashboard'))->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_missing_or_future_session_clock_fails_closed(): void
    {
        $user = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
        $this->actingAs($user)->withSession(['last_authenticated_activity_at' => null])
            ->getJson(route('dashboard'))->assertUnauthorized();
        $this->actingAs($user)->withSession(['last_authenticated_activity_at' => now()->addMinute()->timestamp])
            ->getJson(route('dashboard'))->assertUnauthorized();
    }

    public function test_configured_absolute_lifetime_is_not_reset_by_activity(): void
    {
        config(['account_security.absolute_minutes' => 60]);
        $user = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        foreach (range(1, 3) as $step) {
            $this->travel(19)->minutes();
            $this->get(route('dashboard'))->assertOk();
        }
        $this->travel(3)->minutes();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_platform_uses_approved_fifteen_minute_idle_boundary(): void
    {
        $user = User::factory()->create(['tenant_id' => null, 'password' => Hash::make('platform-password')]);
        DB::table('platform_admins')->insert(['user_id' => $user->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->post(route('platform.login.store'), ['email' => $user->email, 'password' => 'platform-password'])->assertRedirect();
        $this->completeMfa($user, 'platform-password');
        $this->get(route('platform.tenants.index'))->assertOk();
        $this->travel(15)->minutes();
        $this->get(route('platform.tenants.index'))->assertRedirect(route('platform.login'));
        $this->assertGuest();
    }
}
