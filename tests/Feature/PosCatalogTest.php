<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\PlaySession;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_sees_only_active_scoped_catalog_and_server_prices_draft_cart(): void
    {
        [$tenant, $branch, $cashier] = $this->context('cashier');
        $active = Product::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'sku' => 'WATER', 'name' => 'Water', 'type' => 'food_beverage', 'price_minor' => 1500, 'currency' => 'EGP', 'tax_rate_bps' => 1400]);
        Product::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'sku' => 'OLD', 'name' => 'Retired', 'type' => 'merchandise', 'price_minor' => 999, 'status' => 'retired']);
        $foreignTenant = Tenant::factory()->create();
        $foreignBranch = Branch::factory()->create(['tenant_id' => $foreignTenant->id]);
        $foreign = Product::query()->create(['tenant_id' => $foreignTenant->id, 'branch_id' => $foreignBranch->id, 'sku' => 'FOREIGN', 'name' => 'Foreign', 'type' => 'merchandise', 'price_minor' => 1]);

        $this->actingAs($cashier)->get(route('pos.index', ['branch_id' => $branch->id]))
            ->assertOk()->assertSee('Water')->assertDontSee('Retired')->assertDontSee('Foreign');
        $this->actingAs($cashier)->postJson(route('pos.quote'), [
            'branch_id' => $branch->id,
            'lines' => [['product_id' => $active->id, 'quantity' => 2]],
        ])->assertOk()->assertJsonPath('subtotal_minor', 3000)->assertJsonPath('tax_minor', 420)->assertJsonPath('total_minor', 3420);
        $this->actingAs($cashier)->postJson(route('pos.quote'), [
            'branch_id' => $branch->id,
            'lines' => [['product_id' => $active->id, 'quantity' => 1, 'unit_price_minor' => 1, 'tax_minor' => 0]],
        ])->assertUnprocessable();
        $this->actingAs($cashier)->postJson(route('pos.quote'), ['branch_id' => $branch->id, 'lines' => [['product_id' => $foreign->id, 'quantity' => 1]]])->assertStatus(409);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_owner_and_assigned_branch_manager_can_change_catalog_but_cashier_cannot(): void
    {
        [$tenant, $branch, $cashier] = $this->context('cashier');
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $manager = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($manager, ['tenant_id' => $tenant->id, 'role' => 'branch_manager', 'is_active' => true]);

        $payload = ['branch_id' => $branch->id, 'sku' => 'SNACK', 'name' => 'Snack', 'type' => 'food_beverage', 'price_egp' => '25.50'];
        $this->actingAs($cashier)->post(route('pos.products.store'), $payload)->assertForbidden();
        $this->actingAs($manager)->post(route('pos.products.store'), $payload)->assertRedirect()->assertSessionHas('success');
        $product = Product::query()->where('sku', 'SNACK')->firstOrFail();
        $global = Product::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => null, 'sku' => 'GLOBAL-SNACK', 'name' => 'Shared Snack',
            'type' => 'food_beverage', 'price_minor' => 2550, 'currency' => 'EGP',
        ]);
        $this->actingAs($manager)->post(route('pos.products.retire', $global->id), ['branch_id' => $branch->id])->assertForbidden();
        $this->actingAs($owner)->post(route('pos.products.retire', $global->id), ['branch_id' => $branch->id])->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $global->id, 'status' => 'retired']);
        $this->actingAs($owner)->post(route('pos.products.retire', $product->id))->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'retired']);
    }

    public function test_tenant_wide_products_are_visible_and_sellable_at_each_accessible_branch(): void
    {
        [$tenant, $branch, $cashier] = $this->context('cashier');
        $otherBranch = Branch::factory()->create(['tenant_id' => $tenant->id, 'currency' => 'EGP', 'tax_rate_bps' => 1400, 'tax_mode' => 'exclusive', 'is_active' => true]);
        $otherBranch->users()->attach($cashier, ['tenant_id' => $tenant->id, 'role' => 'cashier', 'is_active' => true]);
        $global = Product::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => null, 'sku' => 'GLOBAL-WATER', 'name' => 'Shared Water',
            'type' => 'food_beverage', 'price_minor' => 1500, 'currency' => 'EGP', 'tax_rate_bps' => 1400,
        ]);

        $this->actingAs($cashier)->get(route('pos.index', ['branch_id' => $otherBranch->id]))
            ->assertOk()->assertSee('Shared Water');
        $this->actingAs($cashier)->postJson(route('pos.quote'), [
            'branch_id' => $otherBranch->id,
            'lines' => [['product_id' => $global->id, 'quantity' => 2]],
        ])->assertOk()->assertJsonPath('subtotal_minor', 3000)->assertJsonPath('tax_minor', 420)->assertJsonPath('total_minor', 3420);
    }

    public function test_catalog_creation_form_uses_the_selected_manageable_branch(): void
    {
        [$tenant, $branch] = $this->context('cashier');
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $otherBranch = Branch::factory()->create(['tenant_id' => $tenant->id, 'currency' => 'EGP', 'tax_rate_bps' => 1400, 'tax_mode' => 'exclusive', 'is_active' => true]);

        $content = $this->actingAs($owner)->get(route('pos.index', ['branch_id' => $otherBranch->id]))
            ->assertOk()->getContent();
        $start = strpos($content, 'action="'.route('pos.products.store').'"');
        $this->assertNotFalse($start);
        $end = strpos($content, '</form>', $start);
        $this->assertNotFalse($end);
        $form = substr($content, $start, $end - $start);
        $this->assertStringContainsString('name="branch_id" type="hidden" value="'.$otherBranch->id.'"', $form);
    }

    public function test_only_owner_can_create_a_tenant_wide_product(): void
    {
        [$tenant, $branch] = $this->context('cashier');
        $manager = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($manager, ['tenant_id' => $tenant->id, 'role' => 'branch_manager', 'is_active' => true]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $payload = ['branch_id' => $branch->id, 'sku' => 'GLOBAL-OWNER', 'name' => 'Shared item', 'type' => 'merchandise', 'price_egp' => '10.00', 'tenant_wide' => '1'];

        $this->actingAs($manager)->post(route('pos.products.store'), $payload)->assertForbidden();
        $this->actingAs($owner)->post(route('pos.products.store'), $payload)->assertRedirect();
        $this->assertDatabaseHas('products', ['tenant_id' => $tenant->id, 'branch_id' => null, 'sku' => 'GLOBAL-OWNER']);
    }

    public function test_tenant_wide_sku_is_unique_even_when_branch_id_is_null(): void
    {
        [$tenant, $branch] = $this->context('cashier');
        Product::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => null, 'sku' => 'GLOBAL-SKU', 'name' => 'Shared',
            'type' => 'merchandise', 'price_minor' => 1000, 'currency' => 'EGP',
        ]);

        $this->expectException(QueryException::class);
        Product::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => null, 'sku' => 'global-sku', 'name' => 'Duplicate',
            'type' => 'merchandise', 'price_minor' => 1000, 'currency' => 'EGP',
        ]);
    }

    public function test_catalog_name_is_rendered_as_text_without_an_unsafe_dom_sink(): void
    {
        [$tenant, $branch, $cashier] = $this->context('cashier');
        Product::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'sku' => 'SAFE-NAME',
            'name' => '<img src=x onerror=alert(1)>', 'type' => 'merchandise', 'price_minor' => 1000, 'currency' => 'EGP',
        ]);

        $content = $this->actingAs($cashier)->get(route('pos.index', ['branch_id' => $branch->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $content);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $content);
        $this->assertStringNotContainsString('innerHTML', $content);
    }

    public function test_inclusive_tax_is_extracted_without_inflating_the_total(): void
    {
        [$tenant, $branch, $cashier] = $this->context('cashier');
        $product = Product::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'sku' => 'INCLUSIVE', 'name' => 'Inclusive', 'type' => 'merchandise', 'price_minor' => 10000, 'currency' => 'EGP', 'tax_rate_bps' => 1400, 'tax_mode' => 'inclusive']);

        $this->actingAs($cashier)->postJson(route('pos.quote'), ['branch_id' => $branch->id, 'lines' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertOk()->assertJsonPath('subtotal_minor', 10000)->assertJsonPath('tax_minor', 1228)->assertJsonPath('total_minor', 10000);
    }

    public function test_pos_surface_exposes_server_order_payment_and_receipt_actions(): void
    {
        [, $branch, $cashier] = $this->context('cashier');
        $content = $this->actingAs($cashier)->get(route('pos.index', ['branch_id' => $branch->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-cash-order', $content);
        $this->assertStringContainsString('data-order-url="'.route('pos.orders.store').'"', $content);
        $this->assertStringContainsString('data-payment-url="'.route('pos.orders.payments.store', ['order' => '__ORDER__']).'"', $content);
        $this->assertStringContainsString('data-approval-url="'.route('discount-approvals.request', ['order' => '__ORDER__']).'"', $content);
        $this->assertStringContainsString('data-receipt-url="'.route('receipts.show', ['order' => '__ORDER__']).'"', $content);
        $this->assertStringContainsString('data-create-order', $content);
        $this->assertStringContainsString('data-confirm-payment', $content);
        $this->assertMatchesRegularExpression('/<form[^>]+data-discount-form/', $content);
        $this->assertStringContainsString(__('pos.discount_approvals'), $content);
        $this->assertStringContainsString(__('pos.cash_ready_notice'), $content);
        $this->assertStringNotContainsString('name="amount_minor"', $content);
        $this->assertStringNotContainsString('name="unit_price_minor"', $content);

        $manager = User::factory()->create(['tenant_id' => $cashier->tenant_id, 'status' => 'active']);
        $branch->users()->attach($manager, ['tenant_id' => $cashier->tenant_id, 'role' => 'branch_manager', 'is_active' => true]);
        $managerContent = $this->actingAs($manager)->get(route('pos.index', ['branch_id' => $branch->id]))
            ->assertOk()->getContent();
        $this->assertStringContainsString('type="button" data-create-order', $managerContent);
        $this->assertDoesNotMatchRegularExpression('/<form[^>]+data-discount-form/', $managerContent);

        $receptionist = User::factory()->create(['tenant_id' => $cashier->tenant_id, 'status' => 'active']);
        $branch->users()->attach($receptionist, ['tenant_id' => $cashier->tenant_id, 'role' => 'reception_staff', 'is_active' => true]);
        $receptionContent = $this->actingAs($receptionist)->get(route('pos.index', ['branch_id' => $branch->id]))
            ->assertOk()->getContent();
        $this->assertStringContainsString(__('pos.cashier_only'), $receptionContent);
        $this->assertStringNotContainsString('type="button" data-create-order', $receptionContent);
    }

    public function test_ticket_pos_surface_exposes_eligible_family_labels_masked_phone_and_native_service_date(): void
    {
        [$tenant, $branch, $cashier] = $this->context('cashier');
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id, 'full_name' => 'POS Guardian', 'phone_e164' => '+201012345678',
            'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id,
        ]);
        $child = Child::factory()->create([
            'tenant_id' => $tenant->id, 'full_name' => 'POS Child', 'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id, 'emergency_contact_name' => 'Emergency', 'emergency_contact_phone_e164' => '+201000000000',
        ]);
        DB::table('guardian_child')->insert([
            'tenant_id' => $tenant->id, 'guardian_id' => $guardian->id, 'child_id' => $child->id,
            'relationship_type' => 'legal_guardian', 'can_consent' => true, 'can_check_out' => true,
            'is_primary' => true, 'verification_method' => 'phone_last_four', 'verified_at' => now('UTC'),
            'verified_by_user_id' => $owner->id, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('family_consent_events')->insert([
            'tenant_id' => $tenant->id, 'guardian_id' => $guardian->id, 'child_id' => $child->id,
            'consent_type' => 'child_data', 'status' => 'granted', 'notice_version' => 'test',
            'purpose_snapshot' => 'test', 'data_categories_snapshot' => 'test', 'locale' => 'en',
            'method' => 'staff', 'actor_user_id' => $owner->id, 'branch_id' => $branch->id,
            'request_id' => (string) Str::uuid(), 'occurred_at' => now('UTC'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $rule = PricingRule::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'created_by_user_id' => $owner->id, 'currency' => 'EGP']);
        $type = TicketType::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'pricing_rule_id' => $rule->id,
            'code' => 'POS-'.Str::random(6), 'name' => 'POS ticket', 'price_minor' => 15000,
            'currency' => 'EGP', 'max_uses' => 1, 'status' => 'active', 'created_by_user_id' => $owner->id,
        ]);
        $serviceDate = now('Africa/Cairo')->addDay()->toDateString();
        $this->actingAs($cashier)->postJson(route('pos.quote'), [
            'branch_id' => $branch->id, 'guardian_id' => $guardian->id, 'child_id' => $child->id,
            'service_date' => $serviceDate,
            'lines' => [['ticket_type_id' => $type->id, 'quantity' => 1, 'guardian_id' => $guardian->id, 'child_id' => $child->id, 'service_date' => $serviceDate]],
        ])->assertOk()->assertJsonPath('lines.0.guardian_id', $guardian->id)->assertJsonPath('lines.0.child_id', $child->id)->assertJsonPath('lines.0.service_date', $serviceDate);

        $content = $this->actingAs($cashier)->get(route('pos.index', ['branch_id' => $branch->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('POS Guardian', $content);
        $this->assertStringContainsString('POS Child', $content);
        $this->assertStringContainsString('+20••••5678', $content);
        $this->assertStringNotContainsString('+201012345678', $content);
        $this->assertStringContainsString('data-ticket-family', $content);
        $this->assertStringContainsString('data-service-date', $content);
        $this->assertStringContainsString('type="date"', $content);
        $this->assertStringContainsString('data-hidden-ticket-facts', $content);

        $receptionist = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($receptionist, ['tenant_id' => $tenant->id, 'role' => 'reception_staff', 'is_active' => true]);
        $receptionContent = $this->actingAs($receptionist)->get(route('pos.index', ['branch_id' => $branch->id]))
            ->assertOk()->getContent();
        $this->assertStringNotContainsString('POS Guardian', $receptionContent);
        $this->assertStringNotContainsString('POS Child', $receptionContent);
    }

    public function test_cashier_sees_only_scoped_pending_payment_with_masked_guardian_and_frozen_settlement_contract(): void
    {
        [$tenant, $branch, $cashier] = $this->context('cashier');
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $guardian = Guardian::factory()->create(['tenant_id' => $tenant->id, 'full_name' => 'Private Guardian', 'phone_e164' => '+201012345678', 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        $child = Child::factory()->create(['tenant_id' => $tenant->id, 'full_name' => 'Queue Child', 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        $rule = PricingRule::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'created_by_user_id' => $owner->id, 'currency' => 'EGP']);
        $type = TicketType::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'pricing_rule_id' => $rule->id, 'code' => 'QUEUE-'.Str::random(8), 'name' => 'Queue ticket', 'price_minor' => 20000, 'currency' => 'EGP', 'max_uses' => 1, 'status' => 'active', 'created_by_user_id' => $owner->id]);
        $started = CarbonImmutable::parse('2026-09-14 08:00:00', 'UTC');
        $ticket = Ticket::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'ticket_type_id' => $type->id, 'guardian_id' => $guardian->id, 'child_id' => $child->id,
            'service_date' => '2026-09-14', 'status' => 'issued', 'code_hash' => hash('sha256', Str::uuid()->toString()), 'code_payload_encrypted' => 'opaque', 'display_code' => 'PN-QUEUE-'.Str::random(4),
            'price_minor' => 20000, 'currency' => 'EGP', 'price_snapshot_json' => ['currency' => 'EGP'], 'issued_at' => $started, 'valid_from' => $started, 'valid_until' => $started->addHour(),
            'uses_count' => 0, 'max_uses' => 1, 'idempotency_key' => (string) Str::uuid(), 'issue_fingerprint' => hash('sha256', 'queue'), 'issued_by_user_id' => $owner->id, 'lock_version' => 1,
        ]);
        $session = PlaySession::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'child_id' => $child->id, 'guardian_id' => $guardian->id, 'ticket_id' => $ticket->id, 'pricing_rule_id' => $rule->id,
            'status' => 'pending_payment', 'started_at' => $started, 'expected_end_at' => $started->addHour(), 'pricing_snapshot_json' => ['currency' => 'EGP'], 'created_by_user_id' => $owner->id,
            'lock_version' => 4, 'checkout_snapshot_json' => ['total_minor' => 25650, 'currency' => 'EGP'], 'checkout_amount_due_minor' => 25650,
        ]);

        $content = $this->actingAs($cashier)->get(route('pos.index', ['branch_id' => $branch->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString(__('pos.pending_payments'), $content);
        $this->assertStringContainsString(route('sessions.settle-cash', ['session' => $session->id]), $content);
        $this->assertStringContainsString('256.50 EGP', $content);
        $this->assertStringContainsString('+20••••5678', $content);
        $this->assertStringNotContainsString('+201012345678', $content);
        $this->assertStringContainsString('data-amount-minor="25650"', $content);
        $this->assertStringContainsString('data-lock-version="4"', $content);
        $this->assertStringContainsString('data-settle', $content);
    }

    private function context(string $role): array
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'currency' => 'EGP', 'tax_rate_bps' => 1400, 'tax_mode' => 'exclusive', 'is_active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($user, ['tenant_id' => $tenant->id, 'role' => $role, 'is_active' => true]);

        return [$tenant, $branch, $user];
    }
}
