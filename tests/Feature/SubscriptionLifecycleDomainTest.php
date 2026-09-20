<?php

namespace Tests\Feature;

use App\Actions\SyncSubscriptionStatuses;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionBillingRecord;
use App\Models\Tenant;
use App\Support\SubscriptionAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionLifecycleDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_pointer_is_the_only_authoritative_current_subscription_and_preserves_history(): void
    {
        $tenant = Tenant::factory()->create();
        $old = $this->subscription($tenant, 'expired');
        $current = $this->subscription($tenant, 'active');
        $tenant->update(['current_subscription_id' => $current->id]);

        $tenant->refresh();
        $this->assertSame($current->id, $tenant->currentSubscription->id);
        $this->assertSame(2, $tenant->subscriptions()->count());
        $this->assertTrue(SubscriptionAccess::forTenant($tenant, CarbonImmutable::parse('2026-09-15 UTC'))->allows('write'));
        $this->assertNotSame($old->id, $tenant->currentSubscription->id);
    }

    public function test_cross_tenant_current_pointer_and_billing_record_are_rejected_by_database_foreign_keys(): void
    {
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();
        $foreignSubscription = $this->subscription($second, 'active');

        try {
            $first->update(['current_subscription_id' => $foreignSubscription->id]);
            $this->fail('Cross-tenant current subscription pointer was accepted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $local = $this->subscription($first, 'active');
        try {
            SubscriptionBillingRecord::query()->create([
                'tenant_id' => $first->id,
                'subscription_id' => $foreignSubscription->id,
                'reference' => 'OQ04-CROSS-TENANT',
                'amount_minor' => 100,
                'currency' => 'EGP',
                'billing_period_starts_at' => now('UTC'),
                'billing_period_ends_at' => now('UTC')->addMonth(),
                'paid_at' => now('UTC'),
                'payment_method' => 'bank_transfer',
            ]);
            $this->fail('Cross-tenant billing record was accepted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        SubscriptionBillingRecord::query()->create([
            'tenant_id' => $first->id, 'subscription_id' => $local->id, 'reference' => 'OQ04-LOCAL',
            'amount_minor' => 100, 'currency' => 'EGP', 'billing_period_starts_at' => now('UTC'),
            'billing_period_ends_at' => now('UTC')->addMonth(), 'paid_at' => now('UTC'), 'payment_method' => 'bank_transfer',
        ]);
        $this->assertDatabaseCount('subscription_billing_records', 1);

        try {
            SubscriptionBillingRecord::query()->create([
                'tenant_id' => $first->id, 'subscription_id' => $local->id, 'reference' => 'OQ04-LOCAL',
                'amount_minor' => 100, 'currency' => 'EGP', 'billing_period_starts_at' => now('UTC'),
                'billing_period_ends_at' => now('UTC')->addMonth(), 'paid_at' => now('UTC'), 'payment_method' => 'bank_transfer',
            ]);
            $this->fail('Duplicate tenant invoice reference was accepted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_sync_materializes_grace_then_expiry_idempotently_and_audits_as_system(): void
    {
        $tenant = Tenant::factory()->create();
        $subscription = $this->subscription($tenant, 'trialing', [
            'trial_ends_at' => CarbonImmutable::parse('2026-09-01 00:00:00 UTC'),
            'grace_ends_at' => CarbonImmutable::parse('2026-09-08 00:00:00 UTC'),
        ]);
        $tenant->update(['current_subscription_id' => $subscription->id, 'billing_status' => 'trial']);
        $sync = app(SyncSubscriptionStatuses::class);

        $first = $sync->run(CarbonImmutable::parse('2026-09-03 00:00:00 UTC'));
        $this->assertSame(['status_changes' => 1, 'expired_overrides' => 0], $first);
        $this->assertSame('grace_period', $subscription->refresh()->status);
        $this->assertSame('grace_period', $tenant->refresh()->billing_status);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'subscription.status.synced', 'actor_type' => 'system', 'actor_user_id' => null]);

        $this->assertSame(['status_changes' => 0, 'expired_overrides' => 0], $sync->run(CarbonImmutable::parse('2026-09-03 00:00:00 UTC')));
        $this->assertSame(['status_changes' => 1, 'expired_overrides' => 0], $sync->run(CarbonImmutable::parse('2026-09-09 00:00:00 UTC')));
        $this->assertSame('expired', $subscription->refresh()->status);
        $this->assertSame('expired', $tenant->refresh()->billing_status);
    }

    public function test_sync_materializes_missing_grace_end_from_the_approved_seven_day_policy(): void
    {
        $tenant = Tenant::factory()->create();
        $subscription = $this->subscription($tenant, 'active', [
            'current_period_ends_at' => CarbonImmutable::parse('2026-09-01 00:00:00 UTC'),
            'grace_ends_at' => null,
        ]);

        app(SyncSubscriptionStatuses::class)->run(CarbonImmutable::parse('2026-09-02 00:00:00 UTC'));

        $this->assertSame('grace_period', $subscription->refresh()->status);
        $this->assertTrue($subscription->grace_ends_at->equalTo(CarbonImmutable::parse('2026-09-08 00:00:00 UTC')));
    }

    public function test_temporary_limit_override_stops_affecting_access_and_is_audited_when_expired(): void
    {
        $tenant = Tenant::factory()->create();
        $subscription = $this->subscription($tenant, 'active', [
            'custom_limits_json' => ['branches' => 9, 'users' => 12],
            'custom_limits_override_until' => CarbonImmutable::parse('2026-09-16 00:00:00 UTC'),
            'custom_limits_reason' => 'Temporary launch capacity',
        ]);
        $tenant->update(['current_subscription_id' => $subscription->id]);

        $this->assertSame(9, SubscriptionAccess::forTenant($tenant->fresh(), CarbonImmutable::parse('2026-09-15 UTC'))->limitFor('branches'));
        $this->assertSame(3, SubscriptionAccess::forTenant($tenant->fresh(), CarbonImmutable::parse('2026-09-17 UTC'))->limitFor('branches'));

        $result = app(SyncSubscriptionStatuses::class)->run(CarbonImmutable::parse('2026-09-17 UTC'));
        $this->assertSame(1, $result['expired_overrides']);
        $this->assertNull($subscription->refresh()->custom_limits_json);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'subscription.commercial_override.expired', 'actor_type' => 'system']);
    }

    private function subscription(Tenant $tenant, string $status, array $attributes = []): Subscription
    {
        $plan = Plan::factory()->create(['limits_json' => ['branches' => 3, 'users' => 15]]);
        $now = CarbonImmutable::parse('2026-09-15 12:00:00 UTC');

        return Subscription::factory()->create(array_merge([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => $status,
            'starts_at' => $now->subMonth(), 'current_period_starts_at' => $now->subDay(), 'current_period_ends_at' => $now->addMonth(),
            'trial_ends_at' => $now->addDays(14), 'grace_ends_at' => null,
        ], $attributes));
    }
}
