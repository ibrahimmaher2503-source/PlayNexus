<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PricingRule> */
class PricingRuleFactory extends Factory
{
    protected $model = PricingRule::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'branch_id' => fn (array $attributes): int => Branch::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'code' => fake()->unique()->bothify('RULE-####'),
            'name' => fake()->words(2, true),
            'version' => 1,
            'billing_mode' => 'fixed_duration',
            'base_duration_seconds' => 3600,
            'base_price_minor' => 15000,
            'grace_period_seconds' => 600,
            'overtime_unit_seconds' => 1800,
            'overtime_price_minor' => 5000,
            'currency' => 'EGP',
            'tax_rate_bps' => 0,
            'tax_mode' => 'exclusive',
            'status' => 'active',
            'created_by_user_id' => fn (array $attributes): int => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
        ];
    }
}
