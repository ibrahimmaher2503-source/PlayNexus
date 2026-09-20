<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformSubscriptionAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Route::has('platform.subscriptions.index')) {
            require base_path('routes/platform.php');
        }
    }

    public function test_platform_can_create_a_direct_price_plan_and_toggle_sale_status(): void
    {
        $admin = $this->admin();
        $this->asPlatform($admin)->post(route('platform.plans.store'), [
            'code' => 'STARTER', 'name' => 'Starter', 'description' => 'For a small venue',
            'monthly_price' => '999.00', 'annual_price' => '9990.00', 'annual_discount_bps' => 1667,
            'branches_limit' => 1, 'users_limit' => 5, 'reason' => 'catalog launch',
        ])->assertRedirect(route('platform.plans.index'));

        $plan = Plan::query()->where('code', 'STARTER')->sole();
        $this->assertSame(99900, $plan->monthly_price_minor);
        $this->assertSame(999000, $plan->annual_price_minor);
        $this->assertSame(['branches' => 1, 'users' => 5], $plan->limits_json);
        $this->asPlatform($admin)->patch(route('platform.plans.update', $plan), ['code' => 'STARTER', 'name' => 'Starter Plus', 'description' => 'Updated commercial copy', 'monthly_price_minor' => 109900, 'annual_price_minor' => 1099000, 'annual_discount_bps' => 1670, 'limits' => ['branches' => 2, 'users' => 6], 'reason' => 'catalog correction'])->assertRedirect();
        $plan->refresh();
        $this->assertSame('Starter Plus', $plan->name);
        $this->assertSame(109900, $plan->monthly_price_minor);
        $this->assertSame(['branches' => 2, 'users' => 6], $plan->limits_json);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'plan.updated', 'subject_id' => (string) $plan->id]);
        $this->asPlatform($admin)->patch(route('platform.plans.status', $plan), ['expected_status' => 'active', 'status' => 'inactive', 'reason' => 'not for sale'])->assertRedirect();
        $this->assertSame('inactive', $plan->fresh()->status);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'plan.status.changed', 'subject_id' => (string) $plan->id]);
    }

    public function test_assign_creates_price_snapshot_and_stale_duplicate_assignment_is_rejected(): void
    {
        $admin = $this->admin();
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->create();
        $payload = ['plan_id' => $plan->id, 'billing_interval' => 'monthly', 'expected_current_subscription_id' => null, 'reason' => 'onboarding'];
        $this->asPlatform($admin)->patch(route('platform.subscriptions.assign', $tenant), $payload)->assertRedirect();
        $subscription = Subscription::query()->sole();
        $this->assertSame($subscription->id, $tenant->fresh()->current_subscription_id);
        $this->assertSame($plan->monthly_price_minor, $subscription->price_amount_minor);
        $this->assertSame(now('UTC')->addDays(14)->toDateString(), $subscription->trial_ends_at->toDateString());

        $this->asPlatform($admin)->patch(route('platform.subscriptions.assign', $tenant), $payload)->assertStatus(409);
        $this->assertSame(1, Subscription::query()->count());
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'subscription.assigned')->count());
    }

    public function test_subscription_index_renders_usage_and_manual_invoice_is_separate_from_pos(): void
    {
        $admin = $this->admin();
        $tenant = Tenant::factory()->create(['name' => 'Venue One']);
        $plan = Plan::factory()->create(['limits_json' => ['branches' => 1, 'users' => 5]]);
        $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id]);
        $tenant->update(['current_subscription_id' => $subscription->id]);
        $this->asPlatform($admin)->get(route('platform.subscriptions.index'))->assertOk()->assertSee('Venue One')->assertSee('0/1');
        $this->asPlatform($admin)->post(route('platform.subscriptions.invoices.store', $subscription), [
            'reference' => 'SAAS-001', 'amount' => '999.00', 'currency' => 'EGP',
            'billing_period_start' => now('UTC')->subMonth()->toDateTimeString(), 'billing_period_end' => now('UTC')->toDateTimeString(), 'payment_date' => now('UTC')->toDateTimeString(),
            'payment_method' => 'bank_transfer', 'reason' => 'received transfer',
        ])->assertRedirect();
        $this->assertDatabaseHas('subscription_billing_records', ['reference' => 'SAAS-001', 'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id, 'amount_minor' => 99900]);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'subscription.invoice.recorded']);
    }

    public function test_current_subscription_lifecycle_limits_and_historical_mutation_protection_are_audited(): void
    {
        $admin = $this->admin();
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->create();
        $historical = Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id]);
        $current = Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'active']);
        $tenant->update(['current_subscription_id' => $current->id]);

        $this->asPlatform($admin)->patch(route('platform.subscriptions.status', $current), ['expected_status' => 'active', 'status' => 'suspended', 'reason' => 'commercial hold'])->assertRedirect();
        $this->assertSame('suspended', $current->fresh()->status);
        $this->asPlatform($admin)->patch(route('platform.subscriptions.status', $current), ['expected_status' => 'suspended', 'status' => 'active', 'reason' => 'payment resolved'])->assertRedirect();
        $current->update(['status' => 'trialing']);
        $this->asPlatform($admin)->post(route('platform.subscriptions.trial', $current), ['trial_ends_at' => now('UTC')->addDays(30)->toDateTimeString(), 'reason' => 'trial extension'])->assertRedirect();
        $this->assertSame('trialing', $current->fresh()->status);
        $this->assertSame(now('UTC')->addDays(37)->toDateString(), $current->fresh()->grace_ends_at->toDateString());
        $this->asPlatform($admin)->patch(route('platform.subscriptions.limits', $current), ['custom_limits' => ['branches' => 9, 'users' => 20], 'override_until' => now('UTC')->addDay()->toDateTimeString(), 'reason' => 'temporary commercial uplift'])->assertRedirect();
        $this->assertSame(['branches' => 9, 'users' => 20], $current->fresh()->custom_limits_json);
        $this->assertSame($admin->id, $current->fresh()->custom_limits_set_by_user_id);
        $this->asPlatform($admin)->patch(route('platform.subscriptions.limits', $historical), ['custom_limits' => ['branches' => 9], 'reason' => 'tamper'])->assertStatus(409);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'subscription.trial.extended', 'subject_id' => (string) $current->id]);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'subscription.limits.overridden', 'subject_id' => (string) $current->id]);
    }

    public function test_non_platform_user_is_denied_and_duplicate_invoice_reference_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id]);
        $tenant->update(['current_subscription_id' => $subscription->id]);
        $this->actingAs($staff)->get(route('platform.subscriptions.index'))->assertForbidden();
        $this->app['auth']->logout();
        $this->flushSession();
        $admin = $this->admin();
        $payload = ['reference' => 'DUP-001', 'amount' => '10.00', 'currency' => 'EGP', 'billing_period_start' => now('UTC')->subDay()->toDateTimeString(), 'billing_period_end' => now('UTC')->toDateTimeString(), 'payment_date' => now('UTC')->toDateTimeString(), 'payment_method' => 'cash', 'reason' => 'manual receipt'];
        $this->asPlatform($admin)->post(route('platform.subscriptions.invoices.store', $subscription), $payload)->assertRedirect();
        $this->asPlatform($admin)->post(route('platform.subscriptions.invoices.store', $subscription), $payload)->assertSessionHasErrors('reference');
        $this->assertSame(1, DB::table('subscription_billing_records')->where('reference', 'DUP-001')->count());
    }

    private function admin(): User
    {
        $user = User::factory()->create(['tenant_id' => null, 'email' => 'platform-'.Str::lower(Str::random(8)).'@example.test', 'password' => 'password']);
        DB::table('platform_admins')->insert(['user_id' => $user->id, 'is_active' => true, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);

        return $user;
    }

    private function asPlatform(User $admin): static
    {
        $this->post(route('platform.login.store'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect();
        $this->completeMfa($admin);

        return $this;
    }
}
