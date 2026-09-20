<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlaySession;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Models\User;
use Database\Seeders\M6PilotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class M6ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_revenue_reconciles_refunds_and_uses_branch_local_half_open_dates(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00 UTC');
        [$tenant, $owner] = $this->owner();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Cairo Branch', 'timezone' => 'Africa/Cairo']);
        $included = $this->paidOrder($tenant, $branch, $owner, 'PN-2026-000001', '2026-09-14 21:30:00', 10000, 1200, 1400, 10200, 10200);
        $this->refund($included[0], $included[1], $owner, 10200);
        $this->paidOrder($tenant, $branch, $owner, 'PN-2026-OLD', '2026-09-14 20:59:59', 5000, 0, 0, 5000, 0);
        [$foreignTenant, $foreignOwner] = $this->owner();
        $foreignBranch = Branch::factory()->create(['tenant_id' => $foreignTenant->id, 'timezone' => 'Africa/Cairo']);
        $this->paidOrder($foreignTenant, $foreignBranch, $foreignOwner, 'FOREIGN-RECEIPT', '2026-09-14 21:30:00', 99999, 0, 0, 99999, 0);

        $this->actingAs($owner)->get(route('reports.index', ['type' => 'revenue', 'branch_id' => $branch->id, 'from' => '2026-09-15', 'to' => '2026-09-15']))
            ->assertOk()->assertSee('PN-2026-000001')->assertDontSee('PN-2026-OLD')->assertDontSee('FOREIGN-RECEIPT')
            ->assertViewHas('summary', fn (array $summary): bool => (int) $summary['paid_orders'] === 1 && (int) $summary['gross_minor'] === 10000 && (int) $summary['discounts_minor'] === 1200 && (int) $summary['tax_minor'] === 1400 && (int) $summary['paid_minor'] === 10200 && (int) $summary['refunded_minor'] === 10200);
    }

    public function test_branch_manager_cannot_infer_or_export_an_unassigned_branch(): void
    {
        [$tenant, $owner] = $this->owner();
        $allowed = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $hidden = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('branch_user')->insert(['tenant_id' => $tenant->id, 'branch_id' => $allowed->id, 'user_id' => $manager->id, 'role' => 'branch_manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->paidOrder($tenant, $hidden, $owner, 'HIDDEN-RECEIPT', now(), 1000, 0, 0, 1000, 0);

        $this->actingAs($manager)->get(route('reports.index', ['type' => 'revenue', 'branch_id' => $hidden->id]))->assertNotFound();
        $this->actingAs($manager)->get(route('reports.export', ['type' => 'revenue', 'branch_id' => $hidden->id]))->assertNotFound();
        $this->actingAs($manager)->get(route('reports.index', ['type' => 'revenue']))->assertOk()->assertDontSee('HIDDEN-RECEIPT');
    }

    public function test_refund_is_reconciled_on_its_execution_date_not_the_original_payment_date(): void
    {
        Carbon::setTestNow('2026-09-16 10:00:00 UTC');
        [$tenant, $owner] = $this->owner();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Africa/Cairo']);
        [$order, $payment] = $this->paidOrder($tenant, $branch, $owner, 'PRIOR-DAY-REFUND', '2026-09-14 21:30:00', 10000, 0, 0, 10000, 10000);
        $this->refund($order, $payment, $owner, 10000);

        $this->actingAs($owner)->get(route('reports.index', ['type' => 'revenue', 'from' => '2026-09-16', 'to' => '2026-09-16']))
            ->assertOk()->assertSee('PRIOR-DAY-REFUND')
            ->assertViewHas('summary', fn (array $summary): bool => (int) $summary['paid_orders'] === 0 && (int) $summary['paid_minor'] === 0 && (int) $summary['refunded_minor'] === 10000 && (int) $summary['net_minor'] === -10000);
    }

    public function test_staff_report_applies_manager_scope_per_branch_for_multi_role_users(): void
    {
        [$tenant] = $this->owner();
        $managed = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $cashierBranch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);
        $other = User::factory()->create(['tenant_id' => $tenant->id]);
        foreach ([[$managed, 'branch_manager'], [$cashierBranch, 'cashier']] as [$branch, $role]) {
            DB::table('branch_user')->insert(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'user_id' => $actor->id, 'role' => $role, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->audit($tenant, $managed, $other, 'VISIBLE-MANAGED');
        $this->audit($tenant, $cashierBranch, $actor, 'VISIBLE-SELF');
        $this->audit($tenant, $cashierBranch, $other, 'HIDDEN-CASHIER-BRANCH');

        $this->actingAs($actor)->get(route('reports.index', ['type' => 'staff']))
            ->assertOk()->assertSee('VISIBLE-MANAGED')->assertSee('VISIBLE-SELF')->assertDontSee('HIDDEN-CASHIER-BRANCH');
    }

    public function test_csv_uses_the_same_revenue_filters_and_scope(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00 UTC');
        [$tenant, $owner] = $this->owner();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Africa/Cairo']);
        $this->paidOrder($tenant, $branch, $owner, 'VISIBLE-CSV', '2026-09-14 21:00:00', 2500, 0, 0, 2500, 0);
        $this->paidOrder($tenant, $branch, $owner, 'OLD-CSV', '2026-09-13 21:00:00', 3000, 0, 0, 3000, 0);

        $response = $this->actingAs($owner)->get(route('reports.export', ['type' => 'revenue', 'branch_id' => $branch->id, 'from' => '2026-09-15', 'to' => '2026-09-15']))->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('VISIBLE-CSV', $content);
        $this->assertStringNotContainsString('OLD-CSV', $content);
        $this->assertStringNotContainsString('destination_encrypted', $content);
    }

    public function test_reception_sees_only_authorized_report_navigation(): void
    {
        [$tenant] = $this->owner();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $reception = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('branch_user')->insert(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'user_id' => $reception->id, 'role' => 'reception_staff', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($reception)->get(route('reports.index', ['type' => 'attendance']))
            ->assertOk()->assertDontSee(route('reports.index', ['type' => 'revenue']), false);
        $this->actingAs($reception)->get(route('reports.index', ['type' => 'revenue']))->assertForbidden();
    }

    public function test_all_report_pages_render_in_arabic_rtl_with_bounded_empty_states(): void
    {
        [$tenant, $owner] = $this->owner();
        Branch::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Africa/Cairo']);

        foreach (['revenue', 'attendance', 'sessions', 'staff'] as $type) {
            $this->actingAs($owner)->withSession(['locale' => 'ar'])
                ->get(route('reports.index', ['type' => $type]))
                ->assertOk()->assertSee('lang="ar"', false)->assertSee('dir="rtl"', false)
                ->assertSee(trans('reports.types.'.$type, [], 'ar'))->assertSee(trans('reports.empty', [], 'ar'));
        }

        $this->actingAs($owner)->get(route('reports.index', ['type' => 'revenue', 'from' => '2026-01-01', 'to' => '2026-02-15']))
            ->assertSessionHasErrors('to');
    }

    public function test_session_report_applies_child_ticket_guardian_and_staff_filters(): void
    {
        $this->seed(M6PilotSeeder::class);
        $owner = User::query()->where('email', 'demo.alpha.owner@playnexus.test')->firstOrFail();
        $session = PlaySession::query()->where('tenant_id', $owner->tenant_id)->where('status', 'active')->firstOrFail();
        $phone = DB::table('guardians')->where('id', $session->guardian_id)->value('phone_e164');

        $this->actingAs($owner)->get(route('reports.index', ['type' => 'sessions', 'child_id' => $session->child_id, 'ticket_id' => $session->ticket_id, 'guardian_phone' => $phone, 'actor_user_id' => $session->created_by_user_id]))
            ->assertOk()->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1 && $rows->first()->id === $session->id);
    }

    public function test_revenue_breakdowns_reconcile_each_committed_event_and_use_branch_settings(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00 UTC');
        [$tenant, $owner] = $this->owner();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'America/New_York', 'currency' => 'USD']);
        [$order] = $this->paidOrder($tenant, $branch, $owner, 'BREAKDOWN-RECEIPT', '2026-09-15 12:00:00', 10000, 0, 0, 10000, 0, 'USD');
        $product = Product::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'sku' => 'WATER-REPORT', 'name' => 'Water', 'type' => 'food_beverage', 'price_minor' => 6000, 'currency' => 'USD', 'tax_rate_bps' => 0, 'tax_mode' => 'exclusive', 'status' => 'active', 'lock_version' => 1]);
        $rule = PricingRule::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => 'REPORT-TICKET', 'name' => 'Report ticket', 'version' => 1, 'billing_mode' => 'fixed_duration', 'base_duration_seconds' => 3600, 'base_price_minor' => 4000, 'grace_period_seconds' => 600, 'overtime_unit_seconds' => 1800, 'overtime_price_minor' => 2000, 'currency' => 'USD', 'tax_rate_bps' => 0, 'tax_mode' => 'exclusive', 'status' => 'active', 'created_by_user_id' => $owner->id]);
        $type = TicketType::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'pricing_rule_id' => $rule->id, 'code' => 'REPORT-TICKET', 'name' => 'Play ticket', 'price_minor' => 4000, 'currency' => 'USD', 'max_uses' => 1, 'status' => 'active', 'created_by_user_id' => $owner->id]);
        OrderItem::query()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'line_number' => 1, 'item_kind' => 'product', 'product_id' => $product->id, 'description_snapshot' => 'Water', 'quantity' => 1, 'unit_price_minor' => 6000, 'discount_minor' => 0, 'tax_rate_bps' => 0, 'tax_minor' => 0, 'line_total_minor' => 6000, 'currency' => 'USD']);
        OrderItem::query()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'line_number' => 2, 'item_kind' => 'ticket', 'ticket_type_id' => $type->id, 'description_snapshot' => 'Play ticket', 'quantity' => 1, 'unit_price_minor' => 4000, 'discount_minor' => 0, 'tax_rate_bps' => 0, 'tax_minor' => 0, 'line_total_minor' => 4000, 'currency' => 'USD']);

        $this->actingAs($owner)->get(route('reports.index', ['type' => 'revenue', 'branch_id' => $branch->id, 'from' => '2026-09-15', 'to' => '2026-09-15']))
            ->assertOk()->assertSee('USD')->assertSee('America/New_York')
            ->assertViewHas('summaryByCurrency', fn (array $summary): bool => ($summary['USD']['paid_minor'] ?? null) === 10000)
            ->assertViewHas('breakdowns', function (array $breakdowns): bool {
                $paid = collect($breakdowns['products'])->sum('paid_minor') + collect($breakdowns['ticket_types'])->sum('paid_minor');

                return $paid === 10000 && collect($breakdowns['payment_methods'])->sum('paid_minor') === 10000;
            });
    }

    public function test_session_history_exposes_immutable_charge_payment_receipt_and_override_facts(): void
    {
        $this->seed(M6PilotSeeder::class);
        $owner = User::query()->where('email', 'demo.alpha.owner@playnexus.test')->firstOrFail();
        $session = PlaySession::query()->where('tenant_id', $owner->tenant_id)->where('status', 'completed')->firstOrFail();
        DB::table('play_session_adjustments')->insert(['tenant_id' => $owner->tenant_id, 'session_id' => $session->id, 'actor_user_id' => $owner->id, 'extension_units' => 1, 'adjustment_minor' => 7500, 'reason' => 'Report fixture', 'expected_lock_version' => 2, 'applied_lock_version' => 2, 'request_id' => (string) Str::uuid(), 'created_at' => now()]);
        $session->forceFill(['checkout_override_reason' => 'Manager override for report fixture'])->save();

        $this->actingAs($owner)->get(route('reports.index', ['type' => 'sessions', 'child_id' => $session->child_id, 'ticket_id' => $session->ticket_id]))
            ->assertOk()->assertSee('PN-ALPHA-2026-900001')->assertSee('Manager override for report fixture')
            ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1
                && (int) $rows->first()->extension_units === 1
                && (int) $rows->first()->adjustment_minor === 7500
                && (int) $rows->first()->final_charge_minor === 15000
                && $rows->first()->payment_method === 'cash'
                && (int) $rows->first()->payment_minor === 15000
                && $rows->first()->receipt_number === 'PN-ALPHA-2026-900001');
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create(['timezone' => 'Africa/Cairo', 'currency' => 'EGP']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $plan = Plan::factory()->create(['limits_json' => ['branches' => 50, 'users' => 50]]);
        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_ends_at' => now('UTC')->addMonth(),
        ]);
        $tenant->update(['current_subscription_id' => $subscription->id]);

        return [$tenant, $owner];
    }

    /** @return array{Order, Payment} */
    private function paidOrder(Tenant $tenant, Branch $branch, User $actor, string $receipt, mixed $paidAt, int $subtotal, int $discount, int $tax, int $paid, int $refunded, string $currency = 'EGP'): array
    {
        $order = Order::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'receipt_number' => $receipt, 'status' => $refunded ? 'refunded' : 'paid', 'subtotal_minor' => $subtotal, 'discount_minor' => $discount, 'tax_minor' => $tax, 'total_minor' => $paid, 'paid_minor' => $paid, 'refunded_minor' => $refunded, 'currency' => $currency, 'receipt_snapshot_json' => ['receipt_number' => $receipt], 'receipt_issued_at' => $paidAt, 'opened_by_user_id' => $actor->id, 'paid_by_user_id' => $actor->id, 'paid_at' => $paidAt, 'lock_version' => 1, 'creation_idempotency_key' => (string) Str::uuid(), 'creation_fingerprint' => hash('sha256', $receipt)]);
        $payment = Payment::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'order_id' => $order->id, 'method' => 'cash', 'status' => 'posted', 'amount_minor' => $paid, 'currency' => $currency, 'posted_by_user_id' => $actor->id, 'posted_at' => $paidAt, 'idempotency_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', $receipt)]);

        return [$order, $payment];
    }

    private function refund(Order $order, Payment $payment, User $actor, int $amount): void
    {
        DB::table('refunds')->insert(['tenant_id' => $order->tenant_id, 'branch_id' => $order->branch_id, 'order_id' => $order->id, 'payment_id' => $payment->id, 'requested_by_user_id' => $actor->id, 'approved_by_user_id' => $actor->id, 'executed_by_user_id' => $actor->id, 'amount_minor' => $amount, 'currency' => 'EGP', 'reason' => 'Synthetic report fixture', 'status' => 'refunded', 'expected_order_lock_version' => 1, 'request_idempotency_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', 'request'.$order->id), 'execution_idempotency_key' => (string) Str::uuid(), 'execution_fingerprint' => hash('sha256', 'execute'.$order->id), 'requested_at' => now(), 'approved_at' => now(), 'executed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function audit(Tenant $tenant, Branch $branch, User $actor, string $reason): void
    {
        DB::table('audit_logs')->insert(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'actor_user_id' => $actor->id, 'actor_type' => 'user', 'action' => $reason, 'subject_type' => 'user', 'subject_id' => (string) $actor->id, 'outcome' => 'success', 'reason_code' => 'test', 'request_id' => (string) Str::uuid(), 'occurred_at' => now('UTC')]);
    }
}
