<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_can_be_retrieved_and_reprinted_without_new_financial_rows(): void
    {
        [$tenant, $branch, $cashier, $order] = $this->fixture();
        $beforeOrders = DB::table('orders')->count();
        $beforePayments = DB::table('payments')->count();
        $snapshot = $order->receipt_snapshot_json;

        $this->actingAs($cashier)->getJson(route('receipts.show', $order->id))
            ->assertOk()
            ->assertJsonPath('receipt.receipt_number', $order->receipt_number)
            ->assertJsonPath('receipt.verification_reference', $snapshot['verification_reference'])
            ->assertJsonPath('receipt.qr_payload', $snapshot['qr_payload'])
            ->assertJsonPath('refund_status', null);

        $this->actingAs($cashier)->get(route('receipts.show', $order->id))
            ->assertOk()
            ->assertSee('PN-2026-000001')
            ->assertSee($snapshot['verification_reference'])
            ->assertSee('data-pn-ticket-qr', false)
            ->assertSee('window.print', false);

        $this->actingAs($cashier)->postJson(route('receipts.reprint', $order->id), [
            'idempotency_key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('receipt.receipt_number', $order->receipt_number)
            ->assertJsonPath('receipt.verification_reference', $snapshot['verification_reference'])
            ->assertJsonPath('receipt.qr_payload', $snapshot['qr_payload'])
            ->assertHeader('Receipt-Reprinted', 'true');

        $this->actingAs($cashier)->post(route('receipts.reprint', $order->id))
            ->assertOk()
            ->assertSee('PN-2026-000001')
            ->assertSee($snapshot['verification_reference'])
            ->assertSee('window.print', false);

        $this->assertSame($beforeOrders, DB::table('orders')->count());
        $this->assertSame($beforePayments, DB::table('payments')->count());
        $this->assertEqualsCanonicalizing($snapshot, $order->fresh()->receipt_snapshot_json);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'action' => 'receipt.reprinted',
            'actor_user_id' => $cashier->id,
        ]);
    }

    public function test_foreign_order_receipt_is_hidden(): void
    {
        [$tenant, $branch, $cashier] = $this->context();
        $foreignTenant = Tenant::factory()->create(['is_active' => true]);
        $foreignBranch = Branch::factory()->create(['tenant_id' => $foreignTenant->id, 'is_active' => true]);
        $foreignCashier = User::factory()->create(['tenant_id' => $foreignTenant->id, 'status' => 'active']);
        $foreignBranch->users()->attach($foreignCashier, ['tenant_id' => $foreignTenant->id, 'role' => 'cashier', 'is_active' => true]);
        $order = $this->paidOrder($foreignTenant, $foreignBranch, $foreignCashier);

        $this->actingAs($cashier)->getJson(route('receipts.show', $order->id))->assertNotFound();
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'receipt.reprinted')->count());
    }

    public function test_receipt_local_midnight_uses_issuance_timezone_snapshot_after_branch_timezone_changes(): void
    {
        [, $branch, $cashier, $order] = $this->fixture();
        $snapshot = [...$order->receipt_snapshot_json, 'branch_timezone' => 'America/New_York'];
        $order->update(['receipt_snapshot_json' => $snapshot, 'receipt_issued_at' => '2026-03-09 03:30:00']);
        $branch->update(['timezone' => 'Africa/Cairo']);

        foreach (['en', 'ar'] as $locale) {
            $this->actingAs($cashier)->withSession(['locale' => $locale])
                ->get(route('receipts.show', $order->id))
                ->assertOk()
                ->assertSee('2026-03-08 23:30 America/New_York');
        }
        $this->assertSame('America/New_York', $order->fresh()->receipt_snapshot_json['branch_timezone']);
    }

    private function fixture(): array
    {
        [$tenant, $branch, $cashier] = $this->context();

        return [$tenant, $branch, $cashier, $this->paidOrder($tenant, $branch, $cashier)];
    }

    private function context(): array
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'currency' => 'EGP', 'is_active' => true]);
        $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($cashier, ['tenant_id' => $tenant->id, 'role' => 'cashier', 'is_active' => true]);

        return [$tenant, $branch, $cashier];
    }

    private function paidOrder(Tenant $tenant, Branch $branch, User $cashier): Order
    {
        $reference = (string) Str::uuid();
        $order = Order::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'status' => 'paid',
            'subtotal_minor' => 1000, 'tax_minor' => 140, 'total_minor' => 1140, 'paid_minor' => 1140,
            'currency' => 'EGP', 'receipt_number' => 'PN-2026-000001',
            'receipt_snapshot_json' => [
                'receipt_number' => 'PN-2026-000001', 'status' => 'paid',
                'branch_timezone' => $branch->timezone,
                'verification_reference' => $reference, 'qr_payload' => 'PNR1|'.$reference,
            ],
            'receipt_issued_at' => now('UTC'), 'opened_by_user_id' => $cashier->id,
            'paid_by_user_id' => $cashier->id, 'paid_at' => now('UTC'), 'lock_version' => 2,
        ]);
        Payment::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'order_id' => $order->id,
            'method' => 'cash', 'status' => 'posted', 'amount_minor' => 1140, 'currency' => 'EGP',
            'posted_by_user_id' => $cashier->id, 'posted_at' => now('UTC'),
            'idempotency_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', 'payment'),
        ]);

        return $order;
    }
}
