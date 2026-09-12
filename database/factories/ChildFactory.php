<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Child> */
class ChildFactory extends Factory
{
    protected $model = Child::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'full_name' => fake()->name(),
            'date_of_birth' => null,
            'status' => 'active',
            'created_by_user_id' => fn (array $attributes): int => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'updated_by_user_id' => fn (array $attributes): int => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'lock_version' => 1,
        ];
    }
}
