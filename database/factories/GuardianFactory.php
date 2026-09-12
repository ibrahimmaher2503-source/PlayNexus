<?php

namespace Database\Factories;

use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Guardian> */
class GuardianFactory extends Factory
{
    protected $model = Guardian::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'full_name' => fake()->name(),
            'phone_e164' => fake()->unique()->numerify('+2010########'),
            'email' => fake()->safeEmail(),
            'preferred_locale' => 'ar',
            'status' => 'active',
            'created_by_user_id' => fn (array $attributes): int => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'updated_by_user_id' => fn (array $attributes): int => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'lock_version' => 1,
        ];
    }
}
