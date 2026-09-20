<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiscountApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_request_manager_approval_and_single_use_consumption_are_bound_and_audited(): void
    {
        [$tenant, $branch, $owner, $cashier, $manager, $order] = $this->fixture();
        $payload = ['lines' => [['product_id' => $order->items()->value('product_id'), 'quantity' => 1]]];
        $request = $this->actingAs($cashier)->postJson(route('discount-approvals.request', $order), [
            'expected_order_lock_version' => 1, 'discount_minor' => 1000, 'reason' => 'Customer recovery', 'payload' => $payload,
        ])->assertCreated();
        $approvalId = $request->json('approval_id');
        $this->assertDatabaseHas('approval_records', ['id' => $approvalId, 'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'order_id' => $order->id, 'status' => 'requested']);
        $approvalRecord = ApprovalRecord::query()->findOrFail($approvalId);
        $this->assertNotNull($approvalRecord->payload_fingerprint);
        $this->assertSame($order->items()->value('product_id'), $approvalRecord->request_payload_json['items'][0]['product_id'] ?? null);

        $this->actingAs($manager)->postJson(route('discount-approvals.approve', $approvalId))->assertOk()->assertJsonPath('status', 'approved');
        $consumed = $this->actingAs($cashier)->postJson(route('pos.orders.payments.store', $order), [
            'expected_order_lock_version' => 1, 'amount_minor' => 10400, 'currency' => 'EGP', 'idempotency_key' => (string) Str::uuid(), 'approval_id' => $approvalId,
        ])->assertCreated();
        $consumed->assertJsonPath('status', 'paid')->assertJsonPath('amount_minor', 10400);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'discount_minor' => 1000, 'total_minor' => 10400, 'lock_version' => 3, 'discount_reason' => 'Customer recovery']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.discount_consumed', 'subject_id' => (string) $order->id, 'actor_user_id' => $cashier->id]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'discount_minor' => 1000, 'line_total_minor' => 10400]);
        $this->actingAs($cashier)->postJson(route('discount-approvals.consume', $approvalId), ['expected_order_lock_version' => 1, 'discount_minor' => 1000, 'payload' => $payload])->assertStatus(409);
    }

    public function test_self_approval_stale_cart_payload_and_expired_approval_fail_closed(): void
    {
        [$tenant, , , $cashier, $manager, $order] = $this->fixture();
        $payload = ['lines' => [['product_id' => $order->items()->value('product_id'), 'quantity' => 1]]];
        $approvalId = $this->actingAs($cashier)->postJson(route('discount-approvals.request', $order), ['expected_order_lock_version' => 1, 'discount_minor' => 1000, 'reason' => 'Reason', 'payload' => $payload])->json('approval_id');
        $this->actingAs($cashier)->postJson(route('discount-approvals.approve', $approvalId))->assertForbidden();
        $this->actingAs($manager)->postJson(route('discount-approvals.approve', $approvalId))->assertOk();
        $this->actingAs($cashier)->postJson(route('discount-approvals.consume', $approvalId), ['expected_order_lock_version' => 1, 'discount_minor' => 1000, 'payload' => ['changed' => true]])->assertStatus(409);
        DB::table('approval_records')->where('id', $approvalId)->update(['expires_at' => now('UTC')->subMinute()]);
        $this->actingAs($cashier)->postJson(route('discount-approvals.consume', $approvalId), ['expected_order_lock_version' => 1, 'discount_minor' => 1000, 'payload' => $payload])->assertStatus(409);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'discount_minor' => 0, 'total_minor' => 11400, 'lock_version' => 1]);
    }

    public function test_positive_discount_cannot_make_total_zero_and_foreign_or_unassigned_orders_are_hidden(): void
    {
        [$tenant, $branch, , $cashier, , $order] = $this->fixture();
        $body = ['expected_order_lock_version' => 1, 'discount_minor' => 11400, 'reason' => 'Too much', 'payload' => ['line' => 1]];
        $this->actingAs($cashier)->postJson(route('discount-approvals.request', $order), $body)->assertStatus(409);
        $otherBranch = Branch::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $otherOrder = $this->order($tenant, $otherBranch, $order->opened_by_user_id);
        $this->actingAs($cashier)->postJson(route('discount-approvals.request', $otherOrder), $body)->assertNotFound();
        $foreignTenant = Tenant::factory()->create();
        $foreignBranch = Branch::factory()->create(['tenant_id' => $foreignTenant->id]);
        $foreignUser = User::factory()->create(['tenant_id' => $foreignTenant->id]);
        $foreignOrder = $this->order($foreignTenant, $foreignBranch, $foreignUser->id);
        $this->actingAs($cashier)->postJson(route('discount-approvals.request', $foreignOrder), $body)->assertNotFound();
    }

    public function test_discount_request_rejects_payload_tampering_and_unreconciled_lines(): void
    {
        [$tenant, $branch, , $cashier, , $order] = $this->fixture();
        $productId = $order->items()->value('product_id');
        $base = ['expected_order_lock_version' => 1, 'discount_minor' => 100, 'reason' => 'Cart check'];

        $this->actingAs($cashier)->postJson(route('discount-approvals.request', $order), $base + [
            'payload' => ['lines' => [['product_id' => $productId, 'quantity' => 2]]],
        ])->assertStatus(409);
        $this->assertDatabaseCount('approval_records', 0);

        DB::table('order_items')->where('order_id', $order->id)->update(['line_total_minor' => 1]);
        $this->actingAs($cashier)->postJson(route('discount-approvals.request', $order), $base + [
            'payload' => ['lines' => [['product_id' => $productId, 'quantity' => 1]]],
        ])->assertStatus(409);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'discount_minor' => 0]);
    }

    public function test_pos_ui_exposes_request_review_and_approved_payment_states(): void
    {
        [, $branch, , $cashier, $manager, $order] = $this->fixture();
        $approvalId = $this->actingAs($cashier)->postJson(route('discount-approvals.request', $order), [
            'expected_order_lock_version' => 1,
            'discount_minor' => 1000,
            'reason' => 'Visible approval flow',
            'payload' => ['lines' => [['product_id' => $order->items()->value('product_id'), 'quantity' => 1]]],
        ])->assertCreated()->json('approval_id');

        $this->actingAs($manager)->get(route('pos.index', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee(__('pos.discount_approvals'))
            ->assertSee(__('pos.approve_discount'))
            ->assertSee('Visible approval flow');

        $this->actingAs($manager)->post(route('discount-approvals.approve', $approvalId))
            ->assertRedirect(route('pos.index', ['branch_id' => $branch->id]));

        $this->actingAs($cashier)->get(route('pos.index', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee(__('pos.pay_approved_order'))
            ->assertSee('data-approval-id="'.$approvalId.'"', false)
            ->assertSee('data-amount-minor="10400"', false);
    }

    /** @return array{Tenant, Branch, User, User, User, Order} */
    private function fixture(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'currency' => 'EGP', 'is_active' => true]);
        $cashier = $this->staff($tenant, $branch, 'cashier');
        $manager = $this->staff($tenant, $branch, 'branch_manager');
        $order = $this->order($tenant, $branch, $owner->id);

        return [$tenant, $branch, $owner, $cashier, $manager, $order];
    }

    private function staff(Tenant $tenant, Branch $branch, string $role): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch->users()->attach($user, ['tenant_id' => $tenant->id, 'role' => $role, 'is_active' => true]);

        return $user;
    }

    private function order(Tenant $tenant, Branch $branch, int $openedBy): Order
    {
        $product = Product::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'sku' => 'WATER-'.Str::random(8), 'name' => 'Water', 'type' => 'food_beverage', 'price_minor' => 10000, 'currency' => 'EGP', 'tax_rate_bps' => 1400, 'tax_mode' => 'exclusive', 'status' => 'active', 'lock_version' => 1]);
        $order = Order::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'status' => 'draft', 'subtotal_minor' => 10000, 'tax_minor' => 1400, 'total_minor' => 11400, 'currency' => 'EGP', 'opened_by_user_id' => $openedBy, 'lock_version' => 1]);
        OrderItem::query()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'line_number' => 1, 'item_kind' => 'product', 'product_id' => $product->id, 'description_snapshot' => 'Water', 'quantity' => 1, 'unit_price_minor' => 10000, 'discount_minor' => 0, 'tax_rate_bps' => 1400, 'tax_minor' => 1400, 'line_total_minor' => 11400, 'currency' => 'EGP', 'metadata_json' => ['catalog_lock_version' => 1]]);

        return $order;
    }
}
