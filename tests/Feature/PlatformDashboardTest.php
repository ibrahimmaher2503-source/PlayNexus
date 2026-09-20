<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_platform_admin_sees_safe_platform_aggregates_and_actions(): void
    {
        $admin = User::factory()->create(['tenant_id' => null, 'auth_version' => 1, 'mfa_confirmed_at' => now()]);
        DB::table('platform_admins')->insert(['user_id' => $admin->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $tenant = Tenant::factory()->create(['name' => 'Visible Tenant']);
        Branch::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin)->withSession(['platform_auth_version' => 1, 'mfa_auth_version' => 1])
            ->get(route('platform.dashboard'))
            ->assertOk()
            ->assertSee(__('platform_dashboard.title'))
            ->assertSee('Visible Tenant')
            ->assertSee(route('platform.tenants.index'), false)
            ->assertSee(route('platform.plans.index'), false)
            ->assertSee(route('platform.subscriptions.index'), false)
            ->assertSee(route('platform.support-access.index'), false);
    }

    public function test_tenant_user_cannot_enter_platform_dashboard_and_platform_admin_cannot_enter_tenant_dashboard(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($tenantUser)->get(route('platform.dashboard'))->assertForbidden();

        $admin = User::factory()->create(['tenant_id' => null, 'auth_version' => 1, 'mfa_confirmed_at' => now()]);
        DB::table('platform_admins')->insert(['user_id' => $admin->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin)->withSession(['platform_auth_version' => 1, 'mfa_auth_version' => 1])
            ->get(route('dashboard'))->assertNotFound();
    }
}
