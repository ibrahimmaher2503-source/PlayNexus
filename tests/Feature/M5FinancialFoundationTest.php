<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class M5FinancialFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_schema_has_egp_defaults_and_immutable_snapshot_fields(): void
    {
        foreach (['products', 'orders', 'order_items', 'payments', 'branch_sequences'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }

        $this->assertTrue(Schema::hasColumns('branches', ['discount_approval_bps']));
        $this->assertTrue(Schema::hasColumns('orders', ['receipt_snapshot_json', 'receipt_issued_at']));
        $this->assertTrue(Schema::hasColumns('payments', ['idempotency_key', 'request_fingerprint', 'branch_id']));

        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'sku' => 'WATER-500',
            'name' => 'Water',
            'type' => 'food_beverage',
            'price_minor' => 1500,
        ]);
        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'status' => 'paid',
            'subtotal_minor' => 1500,
            'tax_minor' => 0,
            'total_minor' => 1500,
            'paid_minor' => 1500,
            'currency' => 'EGP',
            'receipt_number' => 'BR-2026-000001',
            'receipt_snapshot_json' => ['branch_name' => 'Main', 'cashier_name' => 'Cashier'],
            'receipt_issued_at' => now('UTC'),
            'opened_by_user_id' => $actor->id,
        ]);

        $product->refresh();
        $order->refresh();
        $this->assertSame('EGP', $product->currency);
        $this->assertSame(1500, $product->price_minor);
        $this->assertSame(1500, $order->total_minor);
        $this->assertSame(['branch_name' => 'Main', 'cashier_name' => 'Cashier'], $order->receipt_snapshot_json);
        $this->assertNotNull($order->receipt_issued_at);
        $this->assertSame(0, (int) DB::table('branches')->where('id', $branch->id)->value('discount_approval_bps'));
    }

    public function test_cross_tenant_product_branch_reference_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $foreignBranch = Branch::factory()->create();

        $this->expectException(QueryException::class);
        Product::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $foreignBranch->id,
            'sku' => 'FOREIGN',
            'name' => 'Foreign branch item',
            'type' => 'merchandise',
            'price_minor' => 100,
        ]);
    }

    public function test_branch_sequence_and_receipt_identity_are_unique_in_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);

        BranchSequence::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'sequence_name' => 'receipt:2026',
            'prefix' => 'BR-2026',
        ]);

        $this->order($tenant, $branch, $actor, 'BR-2026-000001');

        $this->expectException(QueryException::class);
        Order::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'status' => 'paid',
            'subtotal_minor' => 1000,
            'total_minor' => 1000,
            'paid_minor' => 1000,
            'currency' => 'EGP',
            'receipt_number' => 'BR-2026-000001',
            'opened_by_user_id' => $actor->id,
        ]);
    }

    public function test_duplicate_branch_sequence_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);

        BranchSequence::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'sequence_name' => 'receipt:2026',
            'prefix' => 'BR-2026',
        ]);

        $this->expectException(QueryException::class);
        BranchSequence::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'sequence_name' => 'receipt:2026',
            'prefix' => 'BR-2026',
        ]);
    }

    public function test_one_payment_per_order_and_idempotency_key_are_database_invariants(): void
    {
        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);
        $order = $this->order($tenant, $branch, $actor, 'BR-2026-000001');

        Payment::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'amount_minor' => 1000,
            'currency' => 'EGP',
            'posted_by_user_id' => $actor->id,
            'posted_at' => now('UTC'),
            'idempotency_key' => 'cash-key-1',
            'request_fingerprint' => hash('sha256', 'cash-key-1'),
        ]);

        $this->expectException(QueryException::class);
        Payment::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'amount_minor' => 1000,
            'currency' => 'EGP',
            'posted_by_user_id' => $actor->id,
            'posted_at' => now('UTC'),
            'idempotency_key' => 'cash-key-2',
            'request_fingerprint' => hash('sha256', 'cash-key-2'),
        ]);
    }

    public function test_idempotency_key_is_unique_across_orders(): void
    {
        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);
        $first = $this->order($tenant, $branch, $actor, 'BR-2026-000001');
        $second = $this->order($tenant, $branch, $actor, 'BR-2026-000002');

        Payment::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'order_id' => $first->id,
            'amount_minor' => 1000,
            'currency' => 'EGP',
            'posted_by_user_id' => $actor->id,
            'posted_at' => now('UTC'),
            'idempotency_key' => 'same-request-key',
            'request_fingerprint' => hash('sha256', 'same-request-key'),
        ]);

        $this->expectException(QueryException::class);
        Payment::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'order_id' => $second->id,
            'amount_minor' => 1000,
            'currency' => 'EGP',
            'posted_by_user_id' => $actor->id,
            'posted_at' => now('UTC'),
            'idempotency_key' => 'same-request-key',
            'request_fingerprint' => hash('sha256', 'same-request-key'),
        ]);
    }

    private function order(Tenant $tenant, Branch $branch, User $actor, string $receipt): Order
    {
        return Order::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'status' => 'draft',
            'subtotal_minor' => 1000,
            'total_minor' => 1000,
            'currency' => 'EGP',
            'receipt_number' => $receipt,
            'opened_by_user_id' => $actor->id,
        ]);
    }
}
