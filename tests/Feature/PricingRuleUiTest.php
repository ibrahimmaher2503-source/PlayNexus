<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PricingRuleUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_page_shows_current_branch_active_rule_and_integer_egp_values(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Main playroom', 'MAIN', 1450, 'inclusive');
        $rule = PricingRule::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'code' => 'PLAY-60',
            'name' => 'Open play',
            'base_duration_seconds' => 3600,
            'base_price_minor' => 12550,
            'overtime_price_minor' => 7500,
            'tax_rate_bps' => 1450,
            'tax_mode' => 'inclusive',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['branch_id' => $branch->id])
            ->get(route('pricing.index'))
            ->assertOk()
            ->assertViewIs('pricing.index')
            ->assertSee($tenant->name)
            ->assertSee($branch->name)
            ->assertSee($branch->code)
            ->assertSee($rule->name)
            ->assertSee('PLAY-60')
            ->assertSee('60 min')
            ->assertSee('125.50 EGP')
            ->assertSee('75.00 EGP')
            ->assertSee('14.50%')
            ->assertSee(__('pricing.fixed_terms_heading'))
            ->assertSee(__('pricing.grace_period_value'))
            ->assertSee(__('pricing.overtime_rounding_value'))
            ->assertSee(__('pricing.pause_policy_value'))
            ->assertSee(__('pricing.create_heading'))
            ->assertSee('name="branch_id"', false)
            ->assertSee('name="base_duration_minutes"', false)
            ->assertSee('name="base_price_egp"', false)
            ->assertSee('name="overtime_price_egp"', false)
            ->assertSee('min-h-11', false)
            ->assertSee('role="region"', false)
            ->assertSee(__('pricing.rules_table_caption'));

        $html = $response->getContent();
        $this->assertNotFalse($html);
        $this->assertStringContainsString(route('pricing.store'), $html);
        $this->assertStringNotContainsString('125.5 EGP', $html);
        $this->assertStringNotContainsString('name="effective_from"', $html);
        $this->assertStringNotContainsString('name="ticket_type_id"', $html);
        $this->assertStringNotContainsString('name="pause_billing_mode"', $html);
    }

    public function test_arabic_page_is_rtl_and_localizes_fixed_terms_and_controls(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'فرع اللعب', 'AR-MAIN');

        $this->actingAs($owner)
            ->withSession(['locale' => 'ar', 'branch_id' => $branch->id])
            ->get(route('pricing.index'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('pricing.page_title', [], 'ar'))
            ->assertSee(__('pricing.active_rules_heading', [], 'ar'))
            ->assertSee(__('pricing.grace_period_value', [], 'ar'))
            ->assertSee(__('pricing.overtime_rounding_value', [], 'ar'))
            ->assertSee(__('pricing.pause_policy_value', [], 'ar'))
            ->assertSee(__('pricing.create_heading', [], 'ar'))
            ->assertSee(__('pricing.branch_select_label', [], 'ar'))
            ->assertSee('name="base_price_egp"', false);

    }

    public function test_manage_form_keeps_success_and_validation_feedback_accessible(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Feedback branch', 'FEEDBACK');
        $url = route('pricing.index');

        $this->actingAs($owner)
            ->withSession(['success' => __('pricing.created')])
            ->get($url)
            ->assertOk()
            ->assertSee(__('pricing.created'))
            ->assertSee('role="status"', false)
            ->assertSee('aria-live="polite"', false);

        $this->actingAs($owner)
            ->from($url)
            ->followingRedirects()
            ->post(route('pricing.store'), [
                'branch_id' => $branch->id,
                'code' => 'not valid',
                'name' => 'A valid name',
                'base_duration_minutes' => 60,
                'base_price_egp' => '125.50',
                'overtime_price_egp' => '75.00',
            ])
            ->assertOk()
            ->assertSee(__('pricing.validation_failed'))
            ->assertSee('role="alert"', false)
            ->assertSee('aria-live="assertive"', false)
            ->assertSee(__('pricing.validation.code_invalid'));
    }

    public function test_view_only_staff_sees_pricing_navigation_without_create_controls(): void
    {
        [$tenant] = $this->owner();
        $branch = $this->branch($tenant, 'Reception branch', 'RECEPTION');
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('branch_user')->insert([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $staff->id,
            'role' => 'reception',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        PricingRule::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Reception rule',
        ]);

        $response = $this->actingAs($staff)
            ->withSession(['branch_id' => $branch->id])
            ->get(route('pricing.index'))
            ->assertOk()
            ->assertSee(__('pricing.view_only'))
            ->assertSee(__('pricing.view_only_description'))
            ->assertSee(route('pricing.index'), false)
            ->assertSee($branch->name)
            ->assertSee(__('pricing.active_rules_heading'))
            ->assertDontSee(__('pricing.create_heading'))
            ->assertDontSee('name="base_price_egp"', false)
            ->assertDontSee('name="overtime_price_egp"', false)
            ->assertDontSee('name="code"', false)
            ->assertDontSee('name="effective_from"', false)
            ->assertDontSee('name="ticket_type_id"', false);

        $html = $response->getContent();
        $this->assertNotFalse($html);
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

    private function branch(Tenant $tenant, string $name, string $code, int $taxRate = 0, string $taxMode = 'exclusive'): Branch
    {
        return Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'code' => $code,
            'tax_rate_bps' => $taxRate,
            'tax_mode' => $taxMode,
            'is_active' => true,
        ]);
    }
}
