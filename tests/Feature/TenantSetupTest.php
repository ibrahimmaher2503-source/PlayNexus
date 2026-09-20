<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_a_read_only_setup_guide_for_its_own_tenant(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->readyBranch($tenant);
        User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('branch_user')->insert([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'user_id' => $owner->id,
            'role' => 'branch_manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        PricingRule::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'status' => 'active']);
        DB::table('products')->insert([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'sku' => 'SETUP-1', 'name' => 'Setup product',
            'type' => 'product', 'price_minor' => 1000, 'currency' => 'EGP', 'tax_rate_bps' => 0,
            'tax_mode' => 'exclusive', 'status' => 'active', 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('tenant.setup'))
            ->assertOk()
            ->assertSee(__('setup.title'))
            ->assertSee(__('setup.complete'))
            ->assertSee('1 ready branch(es) out of 1 saved.')
            ->assertSee('assigned to a branch.')
            ->assertDontSee($owner->email)
            ->assertDontSee('Setup product');
    }

    public function test_lower_roles_are_denied_and_foreign_scope_input_is_ignored(): void
    {
        [$tenant, $owner] = $this->owner();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $foreign = Tenant::factory()->create();
        $this->readyBranch($foreign);

        $this->actingAs($staff)->get(route('tenant.setup'))->assertForbidden();
        $this->actingAs($owner)->get(route('tenant.setup', ['tenant_id' => $foreign->id]))
            ->assertOk()
            ->assertSee('0 ready branch(es) out of 0 saved.')
            ->assertDontSee($foreign->name);
    }

    private function readyBranch(Tenant $tenant): Branch
    {
        $branch = Branch::factory()->create([
            'tenant_id' => $tenant->id, 'code' => 'MAIN', 'name' => 'Main branch', 'timezone' => 'Africa/Cairo',
            'capacity' => 20, 'currency' => 'EGP', 'tax_rate_bps' => 1400, 'tax_mode' => 'exclusive',
            'receipt_prefix' => 'MAIN', 'payment_methods' => ['cash'], 'is_active' => true,
        ]);
        foreach (range(1, 7) as $weekday) {
            DB::table('branch_opening_hours')->insert([
                'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'weekday' => $weekday,
                'opens_at' => '09:00:00', 'closes_at' => '18:00:00', 'is_closed' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $branch;
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert([
            'tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$tenant, $owner];
    }
}
