<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PLAN-####'),
            'name' => fake()->words(2, true),
            'status' => 'active',
            'description' => fake()->sentence(),
            'limits_json' => ['branches' => 1, 'users' => 5],
            'features_json' => [],
            'monthly_price_minor' => 99000,
            'annual_price_minor' => 990000,
            'annual_discount_bps' => 1667,
            'currency' => 'EGP',
            'lock_version' => 1,
        ];
    }
}
