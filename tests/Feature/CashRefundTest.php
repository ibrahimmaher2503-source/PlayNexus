<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PlaySession;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_cashier_requests_manager_approves_and_cashier_executes_one_full_same_day_refund(): void
    {
        [$tenant, $branch, $cashier, $manager, $order] = $this->fixture();
        $originalReceipt = $order->receipt_snapshot_json;
        $requestKey = (string) Str::uuid();
        $request = $this->actingAs($cashier)->postJson(route('refunds.request', $order), [
            'expected_order_lock_version' => 2, 'reason' => 'Guest requested cancellation', 'idempotency_key' => $requestKey,
        ])->assertCreated();
        $refundId = $request->json('refund_id');
        $this->actingAs($cashier)->postJson(route('refunds.approve', $refundId))->assertForbidden();
        $this->actingAs($manager)->postJson(route('refunds.approve', $refundId))->assertOk()->assertJsonPath('status', 'approved');
        $executeKey = (string) Str::uuid();
        $payload = ['expected_order_lock_version' => 2, 'idempotency_key' => $executeKey];
        $this->actingAs($cashier)->postJson(route('refunds.execute', $refundId), $payload)->assertOk()->assertJsonPath('status', 'refunded')->assertJsonPath('amount_minor', 25650);
        $this->actingAs($cashier)->postJson(route('refunds.execute', $refundId), $payload)->assertOk()->assertJsonPath('created', false);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'refunded', 'refunded_minor' => 25650, 'lock_version' => 3]);
        $this->assertSame($originalReceipt, $order->fresh()->receipt_snapshot_json);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'action' => 'order.refund_executed', 'actor_user_id' => $cashier->id]);
    }

    public function test_refund_is_denied_after_the_original_branch_business_date_without_mutation(): void
    {
        [, , $cashier, , $order] = $this->fixture();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 01:00:00', 'Africa/Cairo'));
        $this->actingAs($cashier)->postJson(route('refunds.request', $order), [
            'expected_order_lock_version' => 2, 'reason' => 'Late request', 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(409);
        $this->assertDatabaseCount('refunds', 0);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_refund_business_date_uses_the_immutable_receipt_timezone_after_branch_timezone_changes(): void
    {
        [$tenant, $branch, $cashier, , $order] = $this->fixture();
        $branch->update(['timezone' => 'America/Los_Angeles']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-14 22:00:00', 'UTC'));
        $payment = Payment::query()->where('order_id', $order->id)->sole();

        // The new branch timezone still considers both instants September 14,
        // but the Cairo timezone captured on the receipt has crossed midnight.
        $this->assertSame('2026-09-14', CarbonImmutable::parse($payment->posted_at)->setTimezone($branch->fresh()->timezone)->toDateString());
        $this->assertSame('2026-09-14', CarbonImmutable::now($branch->fresh()->timezone)->toDateString());

        $this->actingAs($cashier)->postJson(route('refunds.request', $order), [
            'expected_order_lock_version' => 2, 'reason' => 'Timezone boundary', 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(409);

        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_refund_fails_closed_when_the_receipt_timezone_snapshot_is_missing_or_invalid(): void
    {
        [, , $cashier, , $order] = $this->fixture();
        $order->update(['receipt_snapshot_json' => ['total_minor' => 25650, 'branch_timezone' => 'Not/AZone']]);

        $this->actingAs($cashier)->postJson(route('refunds.request', $order), [
            'expected_order_lock_version' => 2, 'reason' => 'Invalid receipt snapshot', 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(409);

        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_unassigned_cashier_cannot_disclose_or_refund_another_branch_order(): void
    {
        [$tenant, , $cashier] = $this->fixture();
        $otherBranch = Branch::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Africa/Cairo', 'currency' => 'EGP', 'is_active' => true]);
        $order = $this->paidOrder($tenant, $otherBranch, User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']));
        $this->actingAs($cashier)->postJson(route('refunds.request', $order), [
            'expected_order_lock_version' => 2, 'reason' => 'Not my branch', 'idempotency_key' => (string) Str::uuid(),
        ])->assertNotFound();
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_tenant_owner_can_approve_a_cashier_request(): void
    {
        [$tenant, , $cashier, , $order] = $this->fixture();
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $refundId = $this->actingAs($cashier)->postJson(route('refunds.request', $order), [
            'expected_order_lock_version' => 2, 'reason' => 'Owner review', 'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->json('refund_id');

        $this->actingAs($owner)->postJson(route('refunds.approve', $refundId))->assertOk()->assertJsonPath('status', 'approved');
    }

    public function test_unused_ticket_linked_to_paid_order_is_refundable(): void
    {
        [$tenant, $branch, $cashier, $manager, $order] = $this->fixture();
        $ticket = $this->ticket($tenant, $branch, $cashier);
        OrderItem::query()->create([
            'tenant_id' => $tenant->id, 'order_id' => $order->id, 'line_number' => 1, 'item_kind' => 'ticket',
            'ticket_type_id' => $ticket->ticket_type_id, 'description_snapshot' => 'Ticket', 'quantity' => 1,
            'unit_price_minor' => 25650, 'line_total_minor' => 25650, 'currency' => 'EGP',
            'metadata_json' => ['ticket_ids' => [$ticket->id]],
        ]);

        $refundId = $this->actingAs($cashier)->postJson(route('refunds.request', $order), [
            'expected_order_lock_version' => 2, 'reason' => 'Unused ticket return', 'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('status', 'requested')->json('refund_id');
        $this->actingAs($manager)->postJson(route('refunds.approve', $refundId))->assertOk();
        $this->actingAs($cashier)->postJson(route('refunds.execute', $refundId), [
            'expected_order_lock_version' => 2, 'idempotency_key' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('status', 'refunded');
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'cancelled', 'cancellation_reason' => 'financial_refund']);
    }

    public function test_scanned_or_session_linked_ticket_blocks_refund(): void
    {
        [$tenant, $branch, $cashier, , $order] = $this->fixture();
        $ticket = $this->ticket($tenant, $branch, $cashier);
        OrderItem::query()->create([
            'tenant_id' => $tenant->id, 'order_id' => $order->id, 'line_number' => 1, 'item_kind' => 'ticket',
            'ticket_type_id' => $ticket->ticket_type_id, 'description_snapshot' => 'Ticket', 'quantity' => 1,
            'unit_price_minor' => 25650, 'line_total_minor' => 25650, 'currency' => 'EGP',
            'metadata_json' => ['ticket_ids' => [$ticket->id]],
        ]);
        DB::table('ticket_scans')->insert([
            'tenant_id' => $tenant->id, 'ticket_id' => $ticket->id, 'branch_id' => $branch->id,
            'scanned_by_user_id' => $cashier->id, 'scanned_at' => now('UTC'), 'scan_purpose' => 'validate',
            'result' => 'accepted', 'code_hash' => $ticket->code_hash, 'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', 'scan'), 'request_id' => (string) Str::uuid(),
            'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);

        $this->actingAs($cashier)->postJson(route('refunds.request', $order), [
            'expected_order_lock_version' => 2, 'reason' => 'Scanned ticket return', 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(409);
        $this->assertDatabaseCount('refunds', 0);

        DB::table('ticket_scans')->delete();
        PlaySession::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'child_id' => $ticket->child_id,
            'guardian_id' => $ticket->guardian_id, 'ticket_id' => $ticket->id, 'pricing_rule_id' => $ticket->type->pricing_rule_id,
            'status' => 'active', 'started_at' => now('UTC'), 'expected_end_at' => now('UTC')->addHour(),
            'pricing_snapshot_json' => ['currency' => 'EGP'], 'created_by_user_id' => $cashier->id,
        ]);
        $this->actingAs($cashier)->postJson(route('refunds.request', $order), [
            'expected_order_lock_version' => 2, 'reason' => 'Session ticket return', 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(409);
        $this->assertDatabaseCount('refunds', 0);
    }

    private function fixture(): array
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-14 12:00:00', 'Africa/Cairo'));
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Africa/Cairo', 'currency' => 'EGP', 'is_active' => true]);
        $cashier = $this->staff($tenant, $branch, 'cashier');
        $manager = $this->staff($tenant, $branch, 'branch_manager');

        return [$tenant, $branch, $cashier, $manager, $this->paidOrder($tenant, $branch, $cashier)];
    }

    private function paidOrder(Tenant $tenant, Branch $branch, User $cashier): Order
    {
        $order = Order::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'status' => 'paid', 'subtotal_minor' => 22500,
            'tax_minor' => 3150, 'total_minor' => 25650, 'paid_minor' => 25650, 'currency' => 'EGP',
            'receipt_number' => 'PN-2026-'.Str::random(6), 'receipt_snapshot_json' => ['total_minor' => 25650, 'branch_timezone' => $branch->timezone],
            'receipt_issued_at' => now('UTC'), 'opened_by_user_id' => $cashier->id, 'paid_by_user_id' => $cashier->id,
            'paid_at' => now('UTC'), 'lock_version' => 2,
        ]);
        Payment::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'order_id' => $order->id, 'method' => 'cash',
            'status' => 'posted', 'amount_minor' => 25650, 'currency' => 'EGP', 'posted_by_user_id' => $cashier->id,
            'posted_at' => now('UTC'), 'idempotency_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', Str::uuid()),
        ]);

        return $order;
    }

    private function staff(Tenant $tenant, Branch $branch, string $role): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($user, ['tenant_id' => $tenant->id, 'role' => $role, 'is_active' => true]);

        return $user;
    }

    private function ticket(Tenant $tenant, Branch $branch, User $actor): Ticket
    {
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id, 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id,
        ]);
        $child = Child::factory()->create([
            'tenant_id' => $tenant->id, 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id,
        ]);
        $rule = PricingRule::factory()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'created_by_user_id' => $actor->id,
        ]);
        $type = TicketType::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'pricing_rule_id' => $rule->id,
            'code' => 'REFUND-'.Str::random(8), 'name' => 'Refund ticket', 'price_minor' => 25650,
            'currency' => 'EGP', 'max_uses' => 1, 'status' => 'active', 'created_by_user_id' => $actor->id,
        ]);

        return Ticket::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'ticket_type_id' => $type->id,
            'guardian_id' => $guardian->id, 'child_id' => $child->id, 'service_date' => '2026-09-14',
            'status' => 'issued', 'code_hash' => hash('sha256', Str::uuid()->toString()),
            'code_payload_encrypted' => 'opaque', 'display_code' => 'PN-REF-'.Str::random(8),
            'price_minor' => 25650, 'currency' => 'EGP', 'price_snapshot_json' => [
                'pricing_rule_id' => $rule->id, 'branch_timezone' => 'Africa/Cairo', 'base_duration_seconds' => 3600,
            ],
            'issued_at' => now('UTC'), 'valid_from' => now('UTC'), 'valid_until' => now('UTC')->addHour(),
            'uses_count' => 0, 'max_uses' => 1, 'idempotency_key' => (string) Str::uuid(),
            'issue_fingerprint' => hash('sha256', 'issue'), 'issued_by_user_id' => $actor->id, 'lock_version' => 1,
        ]);
    }
}
