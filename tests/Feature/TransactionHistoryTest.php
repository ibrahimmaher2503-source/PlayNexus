<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_cashier_can_filter_branch_transactions_by_receipt_status_local_date_and_cashier(): void
    {
        [$tenant, $branch, $cashier, $otherCashier] = $this->fixture();
        $today = CarbonImmutable::parse('2026-09-15 10:00:00', 'UTC');
        $todayOrder = $this->paidOrder($tenant, $branch, $cashier, 'PN-TODAY', $today);
        $yesterdayOrder = $this->paidOrder($tenant, $branch, $otherCashier, 'PN-YESTERDAY', CarbonImmutable::parse('2026-09-14 10:00:00', 'UTC'));
        $yesterdayOrder->forceFill(['status' => 'refunded'])->save();

        $response = $this->actingAs($cashier)->get(route('transactions.index', [
            'branch_id' => $branch->id,
            'receipt_number' => 'PN-TODAY',
            'status' => 'paid',
            'local_date' => '2026-09-15',
            'cashier_id' => $cashier->id,
        ]));

        $response->assertOk()->assertSee('PN-TODAY')->assertDontSee('PN-YESTERDAY')->assertSee('Branch local date')->assertSee(__('transactions.eligible'));
    }

    public function test_transaction_history_is_branch_scoped_and_hides_another_branch(): void
    {
        [$tenant, $branch, $cashier] = $this->fixture();
        $otherBranch = Branch::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Africa/Cairo', 'currency' => 'EGP', 'is_active' => true]);
        $otherCashier = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $otherBranch->users()->attach($otherCashier, ['tenant_id' => $tenant->id, 'role' => 'cashier', 'is_active' => true]);
        $foreignOrder = $this->paidOrder($tenant, $otherBranch, $otherCashier, 'PN-OTHER', CarbonImmutable::now('UTC'));

        $this->actingAs($cashier)->get(route('transactions.index', ['branch_id' => $otherBranch->id]))->assertNotFound();
        $this->actingAs($cashier)->get(route('transactions.index'))->assertOk()->assertDontSee('PN-OTHER');
    }

    public function test_receipt_and_history_render_refund_actions_for_the_correct_roles(): void
    {
        [$tenant, $branch, $cashier, $manager, $order, $payment] = $this->fixture(true);
        $requestKey = (string) Str::uuid();
        $refund = Refund::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'order_id' => $order->id, 'payment_id' => $payment->id,
            'requested_by_user_id' => $cashier->id, 'amount_minor' => $payment->amount_minor, 'currency' => 'EGP',
            'reason' => 'Customer requested a return', 'status' => 'requested', 'expected_order_lock_version' => 2,
            'request_idempotency_key' => $requestKey, 'request_fingerprint' => hash('sha256', 'request'), 'requested_at' => now('UTC'),
        ]);

        $cashierResponse = $this->actingAs($cashier)->get(route('receipts.show', $order));
        $cashierResponse->assertOk()->assertSee('Customer requested a return')->assertSee('A different manager or owner must approve')
            ->assertDontSee('action="'.route('refunds.approve', $refund->id).'"', false);

        $managerResponse = $this->actingAs($manager)->get(route('transactions.index', ['branch_id' => $branch->id]));
        $managerResponse->assertOk()->assertSee('Customer requested a return')->assertSee('action="'.route('refunds.approve', $refund->id).'"', false)
            ->assertSee($requestKey);
    }

    public function test_execution_action_is_visible_to_cashier_but_not_the_approving_manager(): void
    {
        [$tenant, $branch, $cashier, $manager, $order, $payment] = $this->fixture(true);
        $refund = Refund::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'order_id' => $order->id, 'payment_id' => $payment->id,
            'requested_by_user_id' => $cashier->id, 'approved_by_user_id' => $manager->id, 'amount_minor' => $payment->amount_minor, 'currency' => 'EGP',
            'reason' => 'Ready for cash return', 'status' => 'approved', 'expected_order_lock_version' => 2,
            'request_idempotency_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', 'request'), 'requested_at' => now('UTC'),
            'approved_at' => now('UTC'),
        ]);

        $executeAction = 'action="'.route('refunds.execute', $refund->id).'"';
        $this->actingAs($manager)->get(route('receipts.show', $order))->assertOk()->assertDontSee($executeAction, false);
        $this->actingAs($cashier)->get(route('receipts.show', $order))->assertOk()->assertSee($executeAction, false)->assertSee('Record cash returned');
    }

    public function test_html_refund_actions_return_to_the_same_immutable_receipt(): void
    {
        [$tenant, $branch, $cashier, $manager, $order] = $this->fixture(true);
        $request = $this->actingAs($cashier)->post(route('refunds.request', $order), [
            'expected_order_lock_version' => 2,
            'reason' => 'Browser refund request',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $request->assertRedirect(route('receipts.show', $order))->assertSessionHas('success');
        $refund = Refund::query()->where('order_id', $order->id)->firstOrFail();

        $this->actingAs($manager)->post(route('refunds.approve', $refund))->assertRedirect(route('receipts.show', $order));
        $this->actingAs($cashier)->post(route('refunds.execute', $refund), [
            'expected_order_lock_version' => 2,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('receipts.show', $order));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'refunded', 'receipt_number' => 'PN-BASE']);
    }

    public function test_transaction_and_receipt_pages_render_in_arabic_rtl_without_raw_keys(): void
    {
        [$tenant, $branch, $cashier, , $order] = $this->fixture();

        $this->withSession(['locale' => 'ar'])
            ->actingAs($cashier)
            ->get(route('transactions.index', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('المعاملات')
            ->assertDontSee('transactions.');

        $this->withSession(['locale' => 'ar'])
            ->actingAs($cashier)
            ->get(route('receipts.show', $order))
            ->assertOk()
            ->assertSee('الإيصال')
            ->assertDontSee('receipts.');
    }

    /** @return array{Tenant, Branch, User, User, Order, Payment} */
    private function fixture(bool $withManager = false): array
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 12:00:00', 'Africa/Cairo'));
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Africa/Cairo', 'currency' => 'EGP', 'is_active' => true]);
        $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($cashier, ['tenant_id' => $tenant->id, 'role' => 'cashier', 'is_active' => true]);
        $otherCashier = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($otherCashier, ['tenant_id' => $tenant->id, 'role' => 'cashier', 'is_active' => true]);
        $manager = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        if ($withManager) {
            $branch->users()->attach($manager, ['tenant_id' => $tenant->id, 'role' => 'branch_manager', 'is_active' => true]);
        }
        $order = $this->paidOrder($tenant, $branch, $cashier, 'PN-BASE', CarbonImmutable::now('UTC'));
        $payment = $order->payments()->firstOrFail();

        return [$tenant, $branch, $cashier, $manager, $order, $payment];
    }

    private function paidOrder(Tenant $tenant, Branch $branch, User $cashier, string $receipt, CarbonImmutable $paidAt): Order
    {
        $order = Order::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'status' => 'paid', 'subtotal_minor' => 1000,
            'tax_minor' => 140, 'total_minor' => 1140, 'paid_minor' => 1140, 'currency' => 'EGP', 'receipt_number' => $receipt,
            'receipt_snapshot_json' => ['receipt_number' => $receipt, 'status' => 'paid', 'total_minor' => 1140, 'branch_timezone' => $branch->timezone],
            'receipt_issued_at' => $paidAt, 'opened_by_user_id' => $cashier->id, 'paid_by_user_id' => $cashier->id,
            'paid_at' => $paidAt, 'lock_version' => 2,
        ]);
        Payment::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'order_id' => $order->id, 'method' => 'cash', 'status' => 'posted',
            'amount_minor' => 1140, 'currency' => 'EGP', 'posted_by_user_id' => $cashier->id, 'posted_at' => $paidAt,
            'idempotency_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', Str::uuid()),
        ]);

        return $order;
    }
}
