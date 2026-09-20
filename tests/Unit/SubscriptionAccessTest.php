<?php

namespace Tests\Unit;

use App\Support\SubscriptionAccess;
use Carbon\CarbonImmutable;
use DomainException;
use InvalidArgumentException;
use Tests\TestCase;

class SubscriptionAccessTest extends TestCase
{
    public function test_active_subscription_allows_writes_before_period_end(): void
    {
        $access = $this->access([
            'status' => 'active',
            'current_period_ends_at' => '2026-10-01 00:00:00 UTC',
        ]);

        $this->assertSame(SubscriptionAccess::NORMAL, $access->phase());
        $this->assertTrue($access->allows('session_create'));
    }

    public function test_expired_period_within_grace_allows_normal_operations(): void
    {
        $access = $this->access([
            'status' => 'active',
            'current_period_ends_at' => '2026-09-14 00:00:00 UTC',
            'grace_ends_at' => '2026-09-21 00:00:00 UTC',
        ]);

        $this->assertTrue($access->isGrace());
        $this->assertTrue($access->allows('sale_create'));
        $this->assertTrue($access->allows('branch_write'));
    }

    public function test_after_grace_is_read_only_and_owner_keeps_historical_reports(): void
    {
        $access = $this->access([
            'status' => 'expired',
            'current_period_ends_at' => '2026-09-01 00:00:00 UTC',
            'grace_ends_at' => '2026-09-08 00:00:00 UTC',
        ]);

        $this->assertTrue($access->isRestricted());
        $this->assertFalse($access->allows('session_create'));
        $this->assertFalse($access->allows('sale_create'));
        $this->assertFalse($access->allows('staff_write'));
        $this->assertFalse($access->allows('branch_write'));
        $this->assertTrue($access->allows('historical_reports', true));
        $this->assertFalse($access->allows('historical_reports', false));
        $this->assertTrue($access->allows('read'));
    }

    public function test_trial_end_enters_grace_and_grace_end_is_restricted(): void
    {
        $trial = $this->access([
            'status' => 'trialing',
            'trial_ends_at' => '2026-09-14 00:00:00 UTC',
            'grace_ends_at' => '2026-09-21 00:00:00 UTC',
        ]);
        $this->assertSame(SubscriptionAccess::GRACE, $trial->phase());

        $afterGrace = $this->access([
            'status' => 'trialing',
            'trial_ends_at' => '2026-09-14 00:00:00 UTC',
            'grace_ends_at' => '2026-09-15 00:00:00 UTC',
        ]);
        $this->assertSame(SubscriptionAccess::RESTRICTED, $afterGrace->phase());
    }

    public function test_expired_period_without_a_materialized_grace_boundary_observes_the_seven_day_policy(): void
    {
        $access = $this->access([
            'status' => 'active',
            'current_period_ends_at' => '2026-09-14 00:00:00 UTC',
        ]);

        $this->assertSame(SubscriptionAccess::GRACE, $access->phase());
    }

    public function test_plan_limits_and_custom_limits_apply_without_touching_existing_counts(): void
    {
        $access = $this->access([
            'status' => 'active',
            'limits' => ['branches' => 3, 'users' => 15],
            'custom_limits' => ['branches' => 5],
        ]);

        $this->assertSame(5, $access->limitFor('branches'));
        $this->assertSame(15, $access->limitFor('users'));
        $this->assertTrue($access->canCreate('branches', 4));
        $this->assertFalse($access->canCreate('branches', 5));
        $this->assertTrue($access->canCreate('users', 14));
        $this->assertFalse($access->canCreate('users', 15));
    }

    public function test_expired_temporary_override_is_ignored_even_when_constructed_for_a_list_view(): void
    {
        $access = $this->access([
            'status' => 'active',
            'current_period_ends_at' => '2026-10-01 00:00:00 UTC',
            'limits' => ['branches' => 3, 'users' => 15],
            'custom_limits' => ['branches' => 9],
            'custom_limits_override_until' => '2026-09-15 00:00:00 UTC',
        ]);

        $this->assertSame(3, $access->limitFor('branches'));
    }

    public function test_missing_known_limit_fails_closed_and_negative_counts_are_rejected(): void
    {
        $access = $this->access(['status' => 'active']);

        try {
            $access->limitFor('branches');
            $this->fail('Missing plan limits must not be unlimited.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(InvalidArgumentException::class);
        $access->canCreate('users', -1);
    }

    public function test_explicit_enterprise_null_limit_is_unlimited(): void
    {
        $access = $this->access([
            'status' => 'active',
            'current_period_ends_at' => '2026-10-01 00:00:00 UTC',
            'limits' => ['branches' => null, 'users' => null],
        ]);

        $this->assertNull($access->limitFor('branches'));
        $this->assertTrue($access->canCreate('users', 100000));
    }

    public function test_unknown_status_fails_closed_but_legacy_unprovisioned_tenant_remains_operational(): void
    {
        $legacy = SubscriptionAccess::fromArray([], $this->now());
        $this->assertTrue($legacy->allows('write'));
        $this->assertNull($legacy->limitFor('branches'));
        $this->assertTrue(SubscriptionAccess::fromArray(['status' => 'unknown'], $this->now())->isRestricted());
    }

    private function access(array $data): SubscriptionAccess
    {
        return SubscriptionAccess::fromArray($data, $this->now());
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-09-15 12:00:00 UTC');
    }
}
