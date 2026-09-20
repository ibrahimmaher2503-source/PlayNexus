<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $startsAt = now('UTC');

        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'trialing',
            'billing_interval' => 'monthly',
            'price_amount_minor' => 99000,
            'price_currency' => 'EGP',
            'price_annual_discount_bps' => 0,
            'custom_limits_json' => null,
            'custom_limits_override_until' => null,
            'custom_limits_reason' => null,
            'custom_limits_set_by_user_id' => null,
            'starts_at' => $startsAt,
            'trial_ends_at' => $startsAt->copy()->addDays(14),
            'grace_ends_at' => $startsAt->copy()->addDays(21),
            'current_period_starts_at' => $startsAt,
            'current_period_ends_at' => $startsAt->copy()->addMonth(),
            'lock_version' => 1,
        ];
    }
}
