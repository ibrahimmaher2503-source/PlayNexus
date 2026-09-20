<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\Branch;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Order;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrdinaryPosOrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_creates_a_priced_draft_and_rejects_client_money_fields(): void
    {
        [$tenant, $branch, $cashier, $product] = $this->fixture();
        $payload = ['branch_id' => $branch->id, 'idempotency_key' => (string) Str::uuid(), 'items' => [['item_kind' => 'product', 'product_id' => $product->id, 'quantity' => 2, 'unit_price_minor' => 1]]];
        $this->actingAs($cashier)->postJson(route('pos.orders.store'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);

        $response = $this->actingAs($cashier)->postJson(route('pos.orders.store'), [
            'branch_id' => $branch->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['item_kind' => 'product', 'product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated();
        $response->assertJsonPath('status', 'draft')->assertJsonPath('subtotal_minor', 3000)->assertJsonPath('tax_minor', 420)->assertJsonPath('total_minor', 3420);
        $this->assertDatabaseHas('order_items', ['tenant_id' => $tenant->id, 'quantity' => 2, 'unit_price_minor' => 1500, 'line_total_minor' => 3420]);
    }

    public function test_exact_cash_payment_is_atomic_and_replays_the_same_receipt(): void
    {
        [$tenant, $branch, $cashier, $product] = $this->fixture();
        $order = $this->createOrder($cashier, $branch, $product);
        $key = (string) Str::uuid();
        $payload = ['expected_order_lock_version' => 1, 'amount_minor' => 3420, 'currency' => 'EGP', 'method' => 'cash', 'idempotency_key' => $key];

        $first = $this->actingAs($cashier)->postJson(route('pos.orders.payments.store', $order), $payload)->assertCreated();
        $first->assertJsonPath('status', 'paid')->assertJsonPath('receipt_number', 'PN-'.now('UTC')->setTimezone('Africa/Cairo')->format('Y').'-000001')
            ->assertJsonPath('receipt.seller_user_id', $cashier->id)
            ->assertJsonPath('receipt.seller_name', $tenant->legal_name ?: $tenant->name)
            ->assertJsonPath('receipt.cashier_user_id', $cashier->id)
            ->assertJsonPath('verification_reference', $first->json('receipt.verification_reference'))
            ->assertJsonPath('qr_payload', 'PNR1|'.$first->json('verification_reference'))
            ->assertJsonMissingPath('order');
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid', 'paid_minor' => 3420]);

        $this->actingAs($cashier)->postJson(route('pos.orders.payments.store', $order), $payload)
            ->assertOk()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('created', false)->assertJsonPath('receipt_number', $first->json('receipt_number'));
        $this->actingAs($cashier)->postJson(route('pos.orders.payments.store', $order), array_merge($payload, ['amount_minor' => 1]))->assertStatus(409);
        $this->actingAs($cashier)->getJson(route('receipts.show', $order))->assertOk()->assertJsonPath('receipt_number', $first->json('receipt_number'));
        $this->assertDatabaseCount('payments', 1);

        $secondOrder = $this->createOrder($cashier, $branch, $product);
        $this->actingAs($cashier)->postJson(route('pos.orders.payments.store', $secondOrder), [
            'expected_order_lock_version' => 1,
            'amount_minor' => 3420,
            'currency' => 'EGP',
            'method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('receipt_number', 'PN-'.now('UTC')->setTimezone('Africa/Cairo')->format('Y').'-000002');
        $this->assertSame(2, DB::table('branch_sequences')->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)->value('current_value'));
    }

    public function test_create_replay_is_idempotent_and_retired_catalog_cannot_be_paid(): void
    {
        [$tenant, $branch, $cashier, $product] = $this->fixture();
        $key = (string) Str::uuid();
        $payload = ['branch_id' => $branch->id, 'idempotency_key' => $key, 'items' => [['item_kind' => 'product', 'product_id' => $product->id, 'quantity' => 1]]];
        $first = $this->actingAs($cashier)->postJson(route('pos.orders.store'), $payload)->assertCreated();
        $this->actingAs($cashier)->postJson(route('pos.orders.store'), $payload)->assertOk()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('created', false)->assertJsonPath('order_id', $first->json('order_id'));
        $this->actingAs($cashier)->postJson(route('pos.orders.store'), array_merge($payload, ['items' => [['item_kind' => 'product', 'product_id' => $product->id, 'quantity' => 2]]]))->assertStatus(409);
        $product->forceFill(['status' => 'retired', 'lock_version' => 2])->save();
        $this->actingAs($cashier)->postJson(route('pos.orders.payments.store', $first->json('order_id')), ['expected_order_lock_version' => 1, 'amount_minor' => 1710, 'currency' => 'EGP', 'idempotency_key' => (string) Str::uuid()])->assertStatus(409);
        $this->assertSame(0, DB::table('payments')->where('tenant_id', $tenant->id)->count());
    }

    public function test_tenant_wide_product_can_be_added_to_a_branch_order(): void
    {
        [$tenant, $branch, $cashier] = array_slice($this->fixture(), 0, 3);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => null, 'sku' => 'GLOBAL-'.Str::random(6),
            'name' => 'Tenant-wide snack', 'type' => 'food_beverage', 'price_minor' => 900,
            'currency' => 'EGP', 'tax_rate_bps' => 0, 'tax_mode' => 'exclusive', 'status' => 'active',
        ]);

        $this->actingAs($cashier)->postJson(route('pos.orders.store'), [
            'branch_id' => $branch->id, 'idempotency_key' => (string) Str::uuid(),
            'items' => [['item_kind' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('total_minor', 900);
    }

    public function test_discount_approval_cannot_be_used_on_a_different_order(): void
    {
        [$tenant, $branch, $cashier, $product, $manager] = $this->fixture(true);
        $first = $this->createOrder($cashier, $branch, $product);
        $second = $this->createOrder($cashier, $branch, $product);
        $approval = $this->actingAs($cashier)->postJson(route('discount-approvals.request', $first), ['expected_order_lock_version' => 1, 'discount_minor' => 100, 'reason' => 'Bound approval', 'payload' => ['items' => [['item_kind' => 'product', 'item_id' => $product->id, 'quantity' => 2]]]])->assertCreated()->json('approval_id');
        $this->actingAs($manager)->postJson(route('discount-approvals.approve', $approval))->assertOk();
        $this->actingAs($cashier)->postJson(route('pos.orders.payments.store', $second), ['expected_order_lock_version' => 1, 'amount_minor' => 3320, 'currency' => 'EGP', 'idempotency_key' => (string) Str::uuid(), 'approval_id' => $approval])->assertStatus(409);
        $this->assertDatabaseHas('orders', ['id' => $second->id, 'status' => 'draft', 'discount_minor' => 0]);
        $this->assertSame(0, DB::table('payments')->where('tenant_id', $tenant->id)->count());
    }

    public function test_payment_consumes_a_persisted_cart_bound_discount_approval(): void
    {
        [$tenant, $branch, $cashier, $product, $manager] = $this->fixture(true);
        $order = $this->createOrder($cashier, $branch, $product);
        $cart = ['items' => [['item_kind' => 'product', 'item_id' => $product->id, 'quantity' => 2]]];
        $approval = $this->actingAs($cashier)->postJson(route('discount-approvals.request', $order), [
            'expected_order_lock_version' => 1, 'discount_minor' => 500, 'reason' => 'Approved customer recovery', 'payload' => $cart,
        ])->assertCreated()->json('approval_id');
        $this->actingAs($manager)->postJson(route('discount-approvals.approve', $approval))->assertOk();

        $payResponse = $this->actingAs($cashier)->postJson(route('pos.orders.payments.store', $order), [
            'expected_order_lock_version' => 1, 'amount_minor' => 2920, 'currency' => 'EGP', 'idempotency_key' => (string) Str::uuid(), 'approval_id' => $approval,
        ]);
        $payResponse->assertCreated()->assertJsonPath('status', 'paid')->assertJsonPath('amount_minor', 2920);
        $this->assertDatabaseHas('approval_records', ['id' => $approval, 'status' => 'consumed', 'consumed_by_user_id' => $cashier->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'discount_minor' => 500, 'total_minor' => 2920, 'status' => 'paid']);
        $this->assertSame(1, DB::table('payments')->where('order_id', $order->id)->count());
        $this->assertNotNull(ApprovalRecord::query()->find($approval));
        $this->assertSame($tenant->id, $branch->tenant_id);
    }

    public function test_ticket_sale_issues_tickets_only_after_payment_and_replays_without_duplicates(): void
    {
        [$tenant, $branch, $cashier, , $manager] = $this->fixture(true);
        $guardian = Guardian::factory()->create(['tenant_id' => $tenant->id, 'created_by_user_id' => $cashier->id, 'updated_by_user_id' => $cashier->id]);
        $child = Child::factory()->create(['tenant_id' => $tenant->id, 'created_by_user_id' => $cashier->id, 'updated_by_user_id' => $cashier->id, 'emergency_contact_name' => 'Emergency', 'emergency_contact_phone_e164' => '+201000000000']);
        DB::table('guardian_child')->insert(['tenant_id' => $tenant->id, 'guardian_id' => $guardian->id, 'child_id' => $child->id, 'relationship_type' => 'legal_guardian', 'can_consent' => true, 'can_check_out' => true, 'is_primary' => true, 'verification_method' => 'phone_last_four', 'verified_at' => now('UTC'), 'verified_by_user_id' => $cashier->id, 'created_by_user_id' => $cashier->id, 'updated_by_user_id' => $cashier->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('family_consent_events')->insert(['tenant_id' => $tenant->id, 'guardian_id' => $guardian->id, 'child_id' => $child->id, 'consent_type' => 'child_data', 'status' => 'granted', 'notice_version' => 'test', 'purpose_snapshot' => 'test', 'data_categories_snapshot' => 'test', 'locale' => 'en', 'method' => 'staff', 'actor_user_id' => $cashier->id, 'branch_id' => $branch->id, 'request_id' => (string) Str::uuid(), 'occurred_at' => now('UTC'), 'created_at' => now(), 'updated_at' => now()]);
        $rule = PricingRule::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'created_by_user_id' => $cashier->id, 'base_price_minor' => 15000, 'currency' => 'EGP', 'tax_rate_bps' => 0]);
        $type = TicketType::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'pricing_rule_id' => $rule->id, 'code' => 'DAYPASS-'.Str::random(4), 'name' => 'Day pass', 'price_minor' => 15000, 'currency' => 'EGP', 'max_uses' => 1, 'status' => 'active', 'created_by_user_id' => $cashier->id]);
        $tomorrow = now('Africa/Cairo')->addDay()->toDateString();
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            DB::table('branch_opening_hours')->insert(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'weekday' => $weekday, 'opens_at' => '08:00:00', 'closes_at' => '22:00:00', 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
        $order = Order::query()->findOrFail($this->actingAs($cashier)->postJson(route('pos.orders.store'), [
            'branch_id' => $branch->id, 'guardian_id' => $guardian->id, 'idempotency_key' => (string) Str::uuid(),
            'items' => [['item_kind' => 'ticket', 'ticket_type_id' => $type->id, 'quantity' => 2, 'guardian_id' => $guardian->id, 'child_id' => $child->id, 'service_date' => $tomorrow]],
        ])->assertCreated()->json('order_id'));
        $this->assertSame(0, DB::table('tickets')->where('tenant_id', $tenant->id)->count());
        $key = (string) Str::uuid();
        $payment = $this->actingAs($cashier)->postJson(route('pos.orders.payments.store', $order), ['expected_order_lock_version' => 1, 'amount_minor' => 30000, 'currency' => 'EGP', 'idempotency_key' => $key])->assertCreated();
        $this->assertSame(2, DB::table('tickets')->where('tenant_id', $tenant->id)->count());
        $this->assertCount(2, $payment->json('ticket_ids'));
        $this->actingAs($cashier)->postJson(route('pos.orders.payments.store', $order), ['expected_order_lock_version' => 1, 'amount_minor' => 30000, 'currency' => 'EGP', 'idempotency_key' => $key])->assertOk();
        $this->assertSame(2, DB::table('tickets')->where('tenant_id', $tenant->id)->count());

        $refund = $this->actingAs($cashier)->postJson(route('refunds.request', $order), [
            'expected_order_lock_version' => 2, 'reason' => 'Unused tickets', 'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->json('refund_id');
        $this->actingAs($manager)->postJson(route('refunds.approve', $refund))->assertOk();
        $this->actingAs($cashier)->postJson(route('refunds.execute', $refund), [
            'expected_order_lock_version' => 2, 'idempotency_key' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('status', 'refunded');
        $this->assertSame(2, DB::table('tickets')->where('tenant_id', $tenant->id)->where('status', 'cancelled')->count());
    }

    public function test_ticket_draft_rejects_missing_ineligible_and_foreign_family_or_branch(): void
    {
        [$tenant, $branch, $cashier] = array_slice($this->fixture(), 0, 3);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $rule = PricingRule::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'created_by_user_id' => $owner->id, 'currency' => 'EGP']);
        $type = TicketType::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'pricing_rule_id' => $rule->id,
            'code' => 'SCOPE-'.Str::random(4), 'name' => 'Scoped ticket', 'price_minor' => 15000,
            'currency' => 'EGP', 'max_uses' => 1, 'status' => 'active', 'created_by_user_id' => $owner->id,
        ]);
        $guardian = Guardian::factory()->create(['tenant_id' => $tenant->id, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        $child = Child::factory()->create(['tenant_id' => $tenant->id, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        $tomorrow = now('Africa/Cairo')->addDay()->toDateString();

        $this->actingAs($cashier)->postJson(route('pos.orders.store'), [
            'branch_id' => $branch->id, 'idempotency_key' => (string) Str::uuid(),
            'items' => [['item_kind' => 'ticket', 'ticket_type_id' => $type->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        $this->actingAs($cashier)->postJson(route('pos.orders.store'), [
            'branch_id' => $branch->id, 'idempotency_key' => (string) Str::uuid(),
            'items' => [['item_kind' => 'ticket', 'ticket_type_id' => $type->id, 'quantity' => 1, 'guardian_id' => $guardian->id, 'child_id' => $child->id, 'service_date' => $tomorrow]],
        ])->assertUnprocessable();

        $foreignTenant = Tenant::factory()->create(['is_active' => true]);
        $foreignUser = User::factory()->create(['tenant_id' => $foreignTenant->id, 'status' => 'active']);
        $foreignGuardian = Guardian::factory()->create(['tenant_id' => $foreignTenant->id, 'created_by_user_id' => $foreignUser->id, 'updated_by_user_id' => $foreignUser->id]);
        $foreignChild = Child::factory()->create(['tenant_id' => $foreignTenant->id, 'created_by_user_id' => $foreignUser->id, 'updated_by_user_id' => $foreignUser->id]);
        $this->actingAs($cashier)->postJson(route('pos.orders.store'), [
            'branch_id' => $branch->id, 'idempotency_key' => (string) Str::uuid(),
            'items' => [['item_kind' => 'ticket', 'ticket_type_id' => $type->id, 'quantity' => 1, 'guardian_id' => $foreignGuardian->id, 'child_id' => $foreignChild->id, 'service_date' => $tomorrow]],
        ])->assertUnprocessable();

        $foreignBranch = Branch::factory()->create(['tenant_id' => $foreignTenant->id, 'currency' => 'EGP', 'is_active' => true]);
        $this->actingAs($cashier)->postJson(route('pos.orders.store'), [
            'branch_id' => $foreignBranch->id, 'idempotency_key' => (string) Str::uuid(),
            'items' => [['item_kind' => 'ticket', 'ticket_type_id' => $type->id, 'quantity' => 1, 'guardian_id' => $guardian->id, 'child_id' => $child->id, 'service_date' => $tomorrow]],
        ])->assertNotFound();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cash_payment_fails_closed_when_cash_is_not_enabled(): void
    {
        [, $branch, $cashier, $product] = $this->fixture();
        $order = $this->createOrder($cashier, $branch, $product);
        $branch->forceFill(['payment_methods' => []])->save();

        $this->actingAs($cashier)->postJson(route('pos.orders.payments.store', $order), [
            'expected_order_lock_version' => 1,
            'amount_minor' => 3420,
            'currency' => 'EGP',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertConflict();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'draft']);
        $this->assertDatabaseCount('payments', 0);
    }

    /** @return array{Tenant, Branch, User, Product, User} */
    private function fixture(bool $withManager = false): array
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'currency' => 'EGP', 'tax_rate_bps' => 1400, 'tax_mode' => 'exclusive', 'is_active' => true]);
        $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($cashier, ['tenant_id' => $tenant->id, 'role' => 'cashier', 'is_active' => true]);
        $manager = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($manager, ['tenant_id' => $tenant->id, 'role' => 'branch_manager', 'is_active' => true]);
        $product = Product::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'sku' => 'WATER-'.Str::random(6), 'name' => 'Water', 'type' => 'food_beverage', 'price_minor' => 1500, 'currency' => 'EGP', 'tax_rate_bps' => 1400, 'tax_mode' => 'exclusive', 'status' => 'active']);

        return [$tenant, $branch, $cashier, $product, $manager];
    }

    private function createOrder(User $cashier, Branch $branch, Product $product): Order
    {
        return Order::query()->findOrFail($this->actingAs($cashier)->postJson(route('pos.orders.store'), [
            'branch_id' => $branch->id, 'idempotency_key' => (string) Str::uuid(), 'items' => [['item_kind' => 'product', 'product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()->json('order_id'));
    }
}
