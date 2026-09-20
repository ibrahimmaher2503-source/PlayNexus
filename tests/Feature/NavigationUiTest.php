<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NavigationUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_navigation_uses_an_accessible_native_dialog_in_both_locales(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);

        foreach (['en', 'ar'] as $locale) {
            $response = $this->actingAs($owner)->withSession(['locale' => $locale])->get(route('dashboard'));
            $response->assertOk()
                ->assertSee('<dialog', false)
                ->assertSee('id="pn-mobile-menu"', false)
                ->assertSee('data-pn-menu-open', false)
                ->assertSee('data-pn-menu-close', false)
                ->assertSee(__('navigation.close_menu'));
        }
    }

    public function test_selected_branch_opens_a_role_dashboard_with_scoped_quick_actions(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Front desk']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($owner)
            ->withSession(['branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('actor_dashboard.titles.owner'))
            ->assertSee(__('actor_dashboard.quick_actions'))
            ->assertSee($branch->name)
            ->assertSee(route('families.index'), false)
            ->assertSee(route('tickets.index'), false)
            ->assertSee(route('reports.index'), false)
            ->assertDontSee(__('actor_dashboard.choose_branch'));
    }

    public function test_desktop_sidebar_is_grouped_collapsible_and_keeps_branch_context_visible(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'PlayNexus QA']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Nora Owner']);
        $branch = Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Downtown',
            'timezone' => 'Africa/Cairo',
        ]);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($owner)
            ->withSession(['branch_id' => $branch->id, 'locale' => 'en'])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee(__('navigation.operations'))
            ->assertSee(__('navigation.sales'))
            ->assertSee(__('navigation.insights'))
            ->assertSee(__('navigation.management'))
            ->assertSee('data-pn-sidebar-toggle', false)
            ->assertSee(__('navigation.collapse_sidebar'))
            ->assertSee('data-pn-branch-clock', false)
            ->assertSee('data-pn-offline-banner', false)
            ->assertSee('data-timezone="Africa/Cairo"', false)
            ->assertSee(route('notifications.index'), false)
            ->assertSee($branch->name)
            ->assertSee($tenant->name);

        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'id="desktop-nav-section-0"'));
        $this->assertSame(1, substr_count($html, 'id="mobile-nav-section-0"'));
        $this->assertStringNotContainsString('id="nav-section-0"', $html);
    }

    public function test_dashboard_snapshot_uses_branch_local_committed_money(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Africa/Cairo', 'currency' => 'EGP']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $order = Order::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'status' => 'paid', 'subtotal_minor' => 15000, 'total_minor' => 15000, 'paid_minor' => 15000, 'refunded_minor' => 2500, 'currency' => 'EGP', 'opened_by_user_id' => $owner->id, 'paid_by_user_id' => $owner->id, 'paid_at' => now()]);
        $payment = Payment::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'order_id' => $order->id, 'status' => 'posted', 'amount_minor' => 15000, 'currency' => 'EGP', 'posted_by_user_id' => $owner->id, 'posted_at' => now(), 'idempotency_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', 'dashboard-payment')]);
        Refund::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'order_id' => $order->id, 'payment_id' => $payment->id, 'status' => 'refunded', 'amount_minor' => 2500, 'currency' => 'EGP', 'reason' => 'Dashboard test', 'requested_by_user_id' => $owner->id, 'approved_by_user_id' => $owner->id, 'executed_by_user_id' => $owner->id, 'expected_order_lock_version' => 1, 'request_idempotency_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', 'dashboard-refund-request'), 'execution_idempotency_key' => (string) Str::uuid(), 'execution_fingerprint' => hash('sha256', 'dashboard-refund-execute'), 'requested_at' => now(), 'approved_at' => now(), 'executed_at' => now()]);

        $this->actingAs($owner)
            ->withSession(['branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-pn-dashboard', false)
            ->assertSee(__('actor_dashboard.branch_overview'))
            ->assertSee('EGP 125.00')
            ->assertSee(__('actor_dashboard.cash_sales'));
    }
}
