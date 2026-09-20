<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Child;
use App\Models\Guardian;
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

class PlaySessionCashSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_settles_the_frozen_amount_once_with_receipt_event_and_audit(): void
    {
        [$tenant, $owner, $branch, $cashier, $session] = $this->pendingSession();
        $key = (string) Str::uuid();

        $response = $this->actingAs($cashier)->postJson($this->url($session), [
            'expected_lock_version' => 2,
            'amount_minor' => 25650,
            'currency' => 'EGP',
            'idempotency_key' => $key,
        ])->assertCreated();

        $response->assertJsonPath('status', 'completed')
            ->assertJsonPath('receipt_number', 'PN-2026-000001')
            ->assertJsonPath('amount_minor', 25650);
        $orderId = (int) $response->json('order_id');
        $paymentId = (int) $response->json('payment_id');
        $this->assertSame('completed', $session->fresh()->status);
        $this->assertSame(3, $session->fresh()->lock_version);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'session_id' => $session->id,
            'status' => 'paid',
            'paid_minor' => 25650,
            'receipt_number' => 'PN-2026-000001',
        ]);
        $this->assertDatabaseHas('order_items', [
            'tenant_id' => $tenant->id,
            'order_id' => $orderId,
            'line_number' => 1,
            'item_kind' => 'session',
            'session_id' => $session->id,
            'line_total_minor' => 25650,
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'tenant_id' => $tenant->id,
            'order_id' => $orderId,
            'method' => 'cash',
            'amount_minor' => 25650,
            'idempotency_key' => $key,
        ]);
        $this->assertSame(1, DB::table('payments')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, DB::table('order_items')->where('tenant_id', $tenant->id)->where('order_id', $orderId)->count());
        $this->assertSame(1, DB::table('branch_sequences')->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)->where('sequence_name', 'receipt-2026')->value('current_value'));
        $this->assertDatabaseHas('play_session_events', [
            'tenant_id' => $tenant->id,
            'session_id' => $session->id,
            'event_type' => 'completed',
            'reason_code' => 'cash_payment',
            'actor_user_id' => $cashier->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'action' => 'session.cash_settled',
            'subject_id' => (string) $session->id,
            'actor_user_id' => $cashier->id,
        ]);
        $receipt = json_decode((string) DB::table('orders')->where('id', $orderId)->value('receipt_snapshot_json'), true);
        $this->assertSame('PN-2026-000001', $receipt['receipt_number']);
        $this->assertSame(25650, $receipt['total_minor']);
        $this->assertSame('cash', $receipt['payment_method']);
        $this->assertSame($owner->id, $receipt['seller_user_id']);
        $this->assertSame($tenant->legal_name ?: $tenant->name, $receipt['seller_name']);
        $this->assertSame($cashier->id, $receipt['cashier_user_id']);
        $this->assertSame($cashier->name, $receipt['cashier_name']);
        $this->assertMatchesRegularExpression('/\A[0-9a-f-]{36}\z/', $receipt['verification_reference']);
        $this->assertSame('PNR1|'.$receipt['verification_reference'], $receipt['qr_payload']);
        $this->assertSame($owner->id, (int) DB::table('orders')->where('id', $orderId)->value('opened_by_user_id'));
        $this->assertSame(4201, $receipt['session_facts']['elapsed_seconds']);
        $this->assertSame(4201, $receipt['session_facts']['billable_seconds']);
        $lineMetadata = json_decode((string) DB::table('order_items')->where('order_id', $orderId)->value('metadata_json'), true);
        $this->assertSame(22500, $lineMetadata['session_facts']['subtotal_minor']);
    }

    public function test_identical_retry_replays_and_changed_key_payload_conflicts_without_mutation(): void
    {
        [$tenant, , $branch, $cashier, $session] = $this->pendingSession();
        $key = (string) Str::uuid();
        $payload = ['expected_lock_version' => 2, 'amount_minor' => 25650, 'currency' => 'EGP', 'idempotency_key' => $key];

        $first = $this->actingAs($cashier)->postJson($this->url($session), $payload)->assertCreated();
        $branch->forceFill(['payment_methods' => []])->save();
        $this->actingAs($cashier)->postJson($this->url($session), $payload)
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('created', false)
            ->assertJsonPath('receipt_number', $first->json('receipt_number'));

        $this->actingAs($cashier)->postJson($this->url($session), array_merge($payload, ['amount_minor' => 25000]))
            ->assertStatus(409);
        $this->assertSame(1, DB::table('payments')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, DB::table('orders')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, DB::table('play_session_events')->where('event_type', 'completed')->count());
    }

    public function test_wrong_role_branch_and_tenant_are_denied_without_financial_rows(): void
    {
        [$tenant, , $branch, $cashier, $session] = $this->pendingSession();
        $reception = $this->staff($tenant, $branch, 'reception_staff');
        [, , , , $foreignSession] = $this->pendingSession();

        $payload = ['expected_lock_version' => 2, 'amount_minor' => 25650, 'currency' => 'EGP', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($reception)->postJson($this->url($session), $payload)->assertForbidden();
        DB::table('branch_user')->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)->where('user_id', $cashier->id)->delete();
        $this->actingAs($cashier)->postJson($this->url($session), array_merge($payload, ['idempotency_key' => (string) Str::uuid()]))->assertNotFound();
        $this->actingAs($cashier)->postJson($this->url($foreignSession), array_merge($payload, ['idempotency_key' => (string) Str::uuid()]))->assertNotFound();
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(0, DB::table('orders')->count());
    }

    public function test_tenant_owner_can_record_the_payment_and_complete_the_session(): void
    {
        [$tenant, $owner, , , $session] = $this->pendingSession();

        $this->actingAs($owner)->postJson($this->url($session), [
            'expected_lock_version' => 2,
            'amount_minor' => 25650,
            'currency' => 'EGP',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('status', 'completed');

        $this->assertSame(1, DB::table('payments')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, DB::table('orders')->where('tenant_id', $tenant->id)->count());
    }

    public function test_assigned_branch_manager_can_record_the_payment_and_complete_the_session(): void
    {
        [, , $branch, , $session] = $this->pendingSession();
        $manager = $this->staff(Tenant::query()->findOrFail($branch->tenant_id), $branch, 'branch_manager');

        $this->actingAs($manager)->postJson($this->url($session), [
            'expected_lock_version' => 2,
            'amount_minor' => 25650,
            'currency' => 'EGP',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('status', 'completed');
    }

    public function test_wrong_amount_and_invalid_snapshot_roll_back_every_write(): void
    {
        [$tenant, , , $cashier, $session] = $this->pendingSession();
        $before = [
            'status' => $session->status,
            'lock_version' => $session->lock_version,
            'events' => DB::table('play_session_events')->count(),
            'audits' => DB::table('audit_logs')->count(),
        ];
        $payload = ['expected_lock_version' => 2, 'amount_minor' => 1, 'currency' => 'EGP', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($cashier)->postJson($this->url($session), $payload)->assertStatus(409);
        $this->assertSame($before['status'], $session->fresh()->status);
        $this->assertSame($before['lock_version'], $session->fresh()->lock_version);
        $this->assertSame(0, DB::table('payments')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, DB::table('orders')->where('tenant_id', $tenant->id)->count());
        $this->assertSame($before['events'], DB::table('play_session_events')->count());
        $this->assertSame($before['audits'], DB::table('audit_logs')->count());

        $session->forceFill(['checkout_snapshot_json' => ['currency' => 'EGP']])->save();
        $this->actingAs($cashier)->postJson($this->url($session), ['expected_lock_version' => 2, 'amount_minor' => 25650, 'currency' => 'EGP', 'idempotency_key' => (string) Str::uuid()])->assertStatus(409);
        $this->assertSame(0, DB::table('payments')->where('tenant_id', $tenant->id)->count());

        $session->forceFill(['checkout_snapshot_json' => ['total_minor' => 25650, 'currency' => 'EGP']])->save();
        $this->actingAs($cashier)->postJson($this->url($session), ['expected_lock_version' => 2, 'amount_minor' => 25650, 'currency' => 'EGP', 'idempotency_key' => (string) Str::uuid()])->assertStatus(409);
        $this->assertSame(0, DB::table('payments')->where('tenant_id', $tenant->id)->count());
    }

    public function test_session_cash_settlement_fails_closed_when_cash_is_not_enabled(): void
    {
        [$tenant, , $branch, $cashier, $session] = $this->pendingSession();
        $branch->forceFill(['payment_methods' => []])->save();

        $this->actingAs($cashier)->postJson($this->url($session), [
            'expected_lock_version' => 2,
            'amount_minor' => 25650,
            'currency' => 'EGP',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertConflict();

        $this->assertSame('pending_payment', $session->fresh()->status);
        $this->assertSame(0, DB::table('payments')->where('tenant_id', $tenant->id)->count());
    }

    private function pendingSession(): array
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Africa/Cairo', 'receipt_prefix' => 'PN', 'currency' => 'EGP', 'is_active' => true]);
        $cashier = $this->staff($tenant, $branch, 'cashier');
        $rule = PricingRule::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'created_by_user_id' => $owner->id, 'currency' => 'EGP']);
        $guardian = Guardian::factory()->create(['tenant_id' => $tenant->id, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        $child = Child::factory()->create(['tenant_id' => $tenant->id, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        $type = TicketType::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'pricing_rule_id' => $rule->id, 'code' => 'SETTLE-'.Str::random(8), 'name' => 'Session', 'price_minor' => 15000, 'currency' => 'EGP', 'max_uses' => 1, 'status' => 'active', 'created_by_user_id' => $owner->id]);
        $ticket = Ticket::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'ticket_type_id' => $type->id, 'guardian_id' => $guardian->id, 'child_id' => $child->id,
            'service_date' => '2026-09-14', 'status' => 'consumed', 'code_hash' => hash('sha256', Str::uuid()->toString()), 'code_payload_encrypted' => 'opaque', 'display_code' => 'PN-SETTLE',
            'price_minor' => 15000, 'currency' => 'EGP', 'price_snapshot_json' => ['pricing_rule_id' => $rule->id, 'branch_timezone' => 'Africa/Cairo', 'base_duration_seconds' => 3600],
            'issued_at' => now(), 'valid_from' => now(), 'valid_until' => now()->addHour(), 'consumed_at' => now(), 'uses_count' => 1, 'max_uses' => 1,
            'idempotency_key' => (string) Str::uuid(), 'issue_fingerprint' => hash('sha256', 'issue'), 'issued_by_user_id' => $owner->id, 'lock_version' => 2,
        ]);
        $started = CarbonImmutable::parse('2026-09-14 08:00:00', 'UTC');
        $quote = ['elapsed_seconds' => 4201, 'included_seconds' => 4200, 'overtime_seconds' => 1, 'overtime_units' => 1, 'subtotal_minor' => 22500, 'tax_minor' => 3150, 'total_minor' => 25650, 'tax_rate_bps' => 1400, 'tax_mode' => 'exclusive', 'currency' => 'EGP'];
        $session = PlaySession::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'child_id' => $child->id, 'guardian_id' => $guardian->id, 'ticket_id' => $ticket->id, 'pricing_rule_id' => $rule->id,
            'status' => 'pending_payment', 'started_at' => $started, 'expected_end_at' => $started->addHour(), 'pricing_snapshot_json' => ['currency' => 'EGP'], 'created_by_user_id' => $owner->id,
            'lock_version' => 2, 'checkout_guardian_id' => $guardian->id, 'checkout_verification_method' => 'phone_last_four', 'checkout_verified_by_user_id' => $owner->id,
            'checkout_verified_at' => $started, 'checkout_prepared_at' => $started, 'checkout_snapshot_json' => $quote, 'checkout_amount_due_minor' => 25650,
        ]);

        return [$tenant, $owner, $branch, $cashier, $session];
    }

    private function staff(Tenant $tenant, Branch $branch, string $role): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($user, ['tenant_id' => $tenant->id, 'role' => $role, 'is_active' => true]);

        return $user;
    }

    private function url(PlaySession $session): string
    {
        return route('sessions.settle-cash', ['session' => $session->id]);
    }
}
