<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionBillingRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SubscriptionBillingRecord> */
class SubscriptionBillingRecordFactory extends Factory
{
    protected $model = SubscriptionBillingRecord::class;

    public function definition(): array
    {
        $periodStartsAt = now('UTC');

        return [
            'tenant_id' => fn (array $attributes): int => Subscription::query()->findOrFail($attributes['subscription_id'])->tenant_id,
            'subscription_id' => Subscription::factory(),
            'reference' => fake()->unique()->bothify('SUB-########'),
            'payment_method' => 'bank_transfer',
            'amount_minor' => 99000,
            'currency' => 'EGP',
            'billing_period_starts_at' => $periodStartsAt,
            'billing_period_ends_at' => $periodStartsAt->copy()->addMonth(),
            'paid_at' => $periodStartsAt,
        ];
    }
}
