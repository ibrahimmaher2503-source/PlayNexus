<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantSubscriptionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_limit_is_enforced_at_the_authoritative_subscription_limit(): void
    {
        [$tenant, $owner, $subscription] = $this->ownerWithSubscription(['branches' => 1, 'users' => 5]);

        $this->actingAs($owner)->post(route('branches.store'), ['name' => 'First branch'])
            ->assertRedirect(route('branches.manage'));

        $this->postJson(route('branches.store'), ['name' => 'Blocked branch'])
            ->assertStatus(402)
            ->assertJsonPath('message', 'The effective branch limit has been reached.');

        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'tenant_id' => $tenant->id]);
    }

    public function test_active_custom_branch_limit_overrides_the_plan_and_expired_override_does_not(): void
    {
        [$tenant, $owner, $subscription] = $this->ownerWithSubscription(['branches' => 1, 'users' => 5]);
        $subscription->update([
            'custom_limits_json' => ['branches' => 2],
            'custom_limits_override_until' => now('UTC')->addDay(),
            'custom_limits_reason' => 'Temporary onboarding capacity',
            'custom_limits_set_by_user_id' => $owner->id,
        ]);

        Branch::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($owner)->post(route('branches.store'), ['name' => 'Second allowed branch'])->assertRedirect();

        $subscription->update(['custom_limits_override_until' => now('UTC')->subSecond()]);
        $this->postJson(route('branches.store'), ['name' => 'Expired override branch'])
            ->assertStatus(402);
        $this->assertDatabaseCount('branches', 2);
    }

    public function test_staff_limit_counts_existing_tenant_users_and_blocks_additional_staff(): void
    {
        [$tenant, $owner] = $this->ownerWithSubscription(['branches' => 1, 'users' => 2]);

        $this->actingAs($owner)->post(route('staff.store'), ['name' => 'Allowed staff', 'email' => 'allowed-staff@example.test'])
            ->assertRedirect(route('staff.index'));

        $this->postJson(route('staff.store'), ['name' => 'Blocked staff', 'email' => 'blocked-staff@example.test'])
            ->assertStatus(402)
            ->assertJsonPath('message', 'The effective staff limit has been reached.');

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseMissing('users', ['email' => 'blocked-staff@example.test']);
        $this->assertDatabaseHas('users', ['tenant_id' => $tenant->id, 'email' => 'allowed-staff@example.test']);
    }

    public function test_restricted_subscription_blocks_operational_writes_but_owner_can_read_reports_and_subscription(): void
    {
        [$tenant, $owner, $subscription] = $this->ownerWithSubscription(['branches' => 2, 'users' => 5]);
        $subscription->update(['status' => 'expired']);
        Branch::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)->postJson(route('branches.store'), ['name' => 'Read only branch'])->assertStatus(402);
        $this->get(route('reports.index', ['type' => 'revenue']))->assertOk();
        $this->get(route('tenant.subscription.show'))
            ->assertOk()
            ->assertSee(__('subscription.restricted_heading'))
            ->assertSee($subscription->plan->name);

        $this->assertDatabaseMissing('branches', ['tenant_id' => $tenant->id, 'name' => 'Read only branch']);
    }

    public function test_suspension_blocks_every_explicit_new_session_or_sale_entry_point_and_reactivation_restores_creation(): void
    {
        [$tenant, $owner, $subscription] = $this->ownerWithSubscription(['branches' => 2, 'users' => 5]);
        $subscription->update(['status' => 'suspended']);

        $this->actingAs($owner)->postJson(route('sessions.check-in'), [])->assertStatus(402);
        $this->postJson(route('tickets.issue'), [])->assertStatus(402);
        $this->postJson(route('pos.quote'), [])->assertStatus(402);
        $this->postJson(route('pos.orders.store'), [])->assertStatus(402);

        $subscription->update([
            'status' => 'active',
            'current_period_ends_at' => now('UTC')->addMonth(),
        ]);

        $this->post(route('branches.store'), ['name' => 'Reactivated branch'])
            ->assertRedirect(route('branches.manage'));
        $this->assertDatabaseHas('branches', ['tenant_id' => $tenant->id, 'name' => 'Reactivated branch']);
    }

    public function test_restricted_historical_reports_are_owner_only(): void
    {
        [$tenant, $owner, $subscription] = $this->ownerWithSubscription(['branches' => 2, 'users' => 5]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('branch_user')->insert([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $manager->id,
            'role' => 'branch_manager',
            'is_active' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
        $subscription->update(['status' => 'expired']);

        $this->actingAs($owner)->get(route('reports.index', ['type' => 'attendance']))->assertOk();
        $this->actingAs($manager)->get(route('reports.index', ['type' => 'attendance']))->assertStatus(402);
    }

    public function test_non_owner_cannot_read_tenant_commercial_context(): void
    {
        [$tenant] = $this->ownerWithSubscription(['branches' => 1, 'users' => 5]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($staff)->get(route('tenant.subscription.show'))->assertForbidden();
    }

    /** @return array{Tenant, User, Subscription} */
    private function ownerWithSubscription(array $limits): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
        $plan = Plan::factory()->create(['limits_json' => $limits]);
        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_ends_at' => now('UTC')->addMonth(),
        ]);
        $tenant->update(['current_subscription_id' => $subscription->id]);

        return [$tenant->fresh(), $owner, $subscription->fresh('plan')];
    }
}
