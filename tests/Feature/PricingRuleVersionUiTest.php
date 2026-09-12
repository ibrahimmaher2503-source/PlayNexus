<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PricingRuleVersionUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_replacement_details_only_for_manageable_active_rules(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        $managedBranch = $this->branch($tenant, 'Managed branch', 'MANAGED');
        $viewOnlyBranch = $this->branch($tenant, 'View-only branch', 'VIEW-ONLY');
        $this->assign($tenant, $manager, $managedBranch, 'branch_manager');
        $this->assign($tenant, $manager, $viewOnlyBranch, 'reception');

        $rule = PricingRule::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $managedBranch->id,
            'code' => 'PLAY-60',
            'name' => 'Open play',
            'version' => 3,
            'base_duration_seconds' => 5400,
            'base_price_minor' => 12345,
            'overtime_price_minor' => 456,
        ]);
        PricingRule::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $viewOnlyBranch->id,
            'code' => 'PLAY-30',
            'name' => 'Reception play',
        ]);

        $response = $this->actingAs($manager)
            ->get(route('pricing.index'))
            ->assertOk()
            ->assertViewIs('pricing.index')
            ->assertSee($managedBranch->name)
            ->assertSee($viewOnlyBranch->name)
            ->assertSee(__('pricing.replacement_summary'))
            ->assertSee(__('pricing.replacement_description'))
            ->assertSee(__('pricing.replacement_history_notice'))
            ->assertSee(__('pricing.current_version'))
            ->assertSee(__('pricing.current_price'))
            ->assertSee('name="expected_version" value="3"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="base_duration_minutes"', false)
            ->assertSee('name="base_price_egp"', false)
            ->assertSee('name="overtime_price_egp"', false)
            ->assertSee('value="90"', false)
            ->assertSee('value="123.45"', false)
            ->assertSee('value="4.56"', false)
            ->assertSee('123.45 EGP')
            ->assertSee('min-h-11', false)
            ->assertDontSee('name="effective_from"', false)
            ->assertDontSee('name="ticket_type_id"', false)
            ->assertDontSee('name="session_id"', false);

        $html = $response->getContent();
        $this->assertNotFalse($html);
        $this->assertStringContainsString(route('pricing.versions.store', $rule), $html);
        $this->assertSame(1, substr_count($html, 'id="pricing-replacement-'));
    }

    public function test_arabic_replacement_copy_and_conflict_success_states_are_accessible(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'فرع التسعير', 'AR-PRICE');
        $rule = PricingRule::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'باقة اللعب',
            'version' => 2,
            'base_price_minor' => 10000,
            'overtime_price_minor' => 2500,
        ]);

        $this->actingAs($owner)
            ->withSession(['locale' => 'ar', 'branch_id' => $branch->id, 'success' => __('pricing.created', [], 'ar')])
            ->get(route('pricing.index'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('pricing.replacement_summary', [], 'ar'))
            ->assertSee(__('pricing.replacement_description', [], 'ar'))
            ->assertSee(__('pricing.replacement_history_notice', [], 'ar'))
            ->assertSee(__('pricing.replacement_submit', [], 'ar'))
            ->assertSee(__('pricing.created', [], 'ar'))
            ->assertSee('name="expected_version" value="2"', false);

        $this->actingAs($owner)
            ->withSession(['locale' => 'ar', 'branch_id' => $branch->id, 'conflict' => __('pricing.conflict', [], 'ar')])
            ->get(route('pricing.index'))
            ->assertOk()
            ->assertSee(__('pricing.replacement_conflict_title', [], 'ar'))
            ->assertSee(__('pricing.conflict', [], 'ar'))
            ->assertSee('id="pricing-conflict"', false)
            ->assertSee('role="alert"', false)
            ->assertSee('aria-live="assertive"', false);

    }

    public function test_view_only_staff_sees_historical_rule_without_replacement_controls(): void
    {
        [$tenant] = $this->owner();
        $branch = $this->branch($tenant, 'Reception pricing', 'RECEPTION');
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->assign($tenant, $staff, $branch, 'reception');
        $rule = PricingRule::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Reception rule',
            'version' => 4,
            'base_price_minor' => 9900,
        ]);

        $response = $this->actingAs($staff)
            ->withSession(['branch_id' => $branch->id])
            ->get(route('pricing.index'))
            ->assertOk()
            ->assertSee($rule->name)
            ->assertSee(__('pricing.view_only'))
            ->assertDontSee('id="pricing-replacement-', false)
            ->assertDontSee(__('pricing.replacement_summary'))
            ->assertDontSee('name="expected_version"', false)
            ->assertDontSee('name="base_price_egp"', false)
            ->assertDontSee('name="overtime_price_egp"', false)
            ->assertDontSee('name="effective_from"', false)
            ->assertDontSee('name="ticket_type_id"', false)
            ->assertDontSee('name="session_id"', false);

        $html = $response->getContent();
        $this->assertNotFalse($html);
        $this->assertStringNotContainsString(route('pricing.versions.store', $rule), $html);
        $this->assertStringNotContainsString(__('pricing.replacement_history_notice'), $html);
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $owner];
    }

    private function branch(Tenant $tenant, string $name, string $code): Branch
    {
        return Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function assign(Tenant $tenant, User $user, Branch $branch, string $role): void
    {
        DB::table('branch_user')->insert([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
