<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_users_default_to_invited_but_factory_staff_is_active(): void
    {
        $tenant = Tenant::factory()->create();

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Invited User',
            'email' => 'invited@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->assertSame('invited', $user->refresh()->status);
        $this->assertSame('active', User::factory()->create()->status);
    }

    public function test_status_migration_backfills_existing_users_as_active(): void
    {
        $migration = require database_path('migrations/2026_09_10_000004_add_status_to_users_table.php');
        $migration->down();
        DB::table('users')->insert([
            'name' => 'Existing User',
            'email' => 'existing@example.test',
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'status'));
        $this->assertSame('active', DB::table('users')->where('email', 'existing@example.test')->value('status'));
    }

    public function test_non_active_staff_cannot_log_in_with_the_generic_failure(): void
    {
        foreach (['invited', 'suspended', 'disabled'] as $status) {
            [$user] = $this->staff($status);

            $this->from(route('login'))
                ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_stale_non_active_staff_is_revoked_on_protected_html_and_json_requests(): void
    {
        foreach (['invited', 'suspended', 'disabled'] as $status) {
            [$user, $branch] = $this->staff();
            $this->actingAs($user)->withSession(['branch_id' => $branch->id]);
            User::query()->whereKey($user)->update(['status' => $status]);

            $this->get(route('dashboard'))
                ->assertRedirect(route('login'))
                ->assertSessionMissing('branch_id');
            $this->assertGuest();

            $this->actingAs($user)->withSession(['branch_id' => $branch->id]);
            $this->getJson('/branches/'.$branch->id)
                ->assertUnauthorized()
                ->assertSessionMissing('branch_id');
            $this->assertGuest();
        }
    }

    public function test_non_active_staff_can_still_log_out(): void
    {
        foreach (['invited', 'suspended', 'disabled'] as $status) {
            [$user, $branch] = $this->staff($status);

            $this->actingAs($user)->withSession(['branch_id' => $branch->id])
                ->post(route('logout'))
                ->assertRedirect(route('login'))
                ->assertSessionMissing('branch_id');
            $this->assertGuest();
        }
    }

    public function test_policy_uses_the_current_persisted_staff_status(): void
    {
        [$user, $branch] = $this->staff();
        $this->assertTrue(Gate::forUser($user)->allows('view', $branch));

        User::query()->whereKey($user)->update(['status' => 'disabled']);

        $this->assertFalse(Gate::forUser($user)->allows('view', $branch));
    }

    /** @return array{User, Branch} */
    private function staff(string $status = 'active'): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('password'),
            'status' => $status,
        ]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $user->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        return [$user, $branch];
    }
}
