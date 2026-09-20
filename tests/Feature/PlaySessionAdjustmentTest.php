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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlaySessionAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_owner_or_assigned_manager_can_append_an_adjustment_and_quote_includes_it(): void
    {
        [$tenant, $owner, $manager, $cashier, $session] = $this->fixture();
        Carbon::setTestNow($session->started_at->addSeconds(4200));

        $payload = [
            'expected_lock_version' => 1,
            'extension_units' => 1,
            'adjustment_minor' => 1000,
            'reason' => 'Approved extra play time',
            'idempotency_key' => (string) Str::uuid(),
        ];
        $response = $this->actingAs($manager)->postJson($this->url($session), $payload);

        $response->assertCreated()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('lock_version', 2)
            ->assertJsonPath('quote.extension_units', 1)
            ->assertJsonPath('quote.extension_seconds', 1800)
            ->assertJsonPath('quote.adjustment_minor', 1000)
            ->assertJsonPath('quote.subtotal_minor', 23500);
        $this->assertDatabaseHas('play_session_adjustments', [
            'tenant_id' => $tenant->id,
            'session_id' => $session->id,
            'actor_user_id' => $manager->id,
            'extension_units' => 1,
            'adjustment_minor' => 1000,
            'expected_lock_version' => 1,
            'applied_lock_version' => 2,
            'reason' => 'Approved extra play time',
        ]);
        $this->assertDatabaseHas('play_session_events', ['event_type' => 'adjusted', 'session_id' => $session->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'session.adjusted', 'subject_id' => (string) $session->id]);

        $this->actingAs($manager)
            ->postJson($this->url($session), $payload)
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertHeader('Idempotent-Replayed', 'true');
        $this->actingAs($manager)
            ->postJson($this->url($session), array_merge($payload, ['adjustment_minor' => 2000]))
            ->assertStatus(409);
        $this->assertDatabaseCount('play_session_commands', 1);

        $this->actingAs($owner)->postJson($this->url($session), [
            'expected_lock_version' => 2,
            'extension_units' => 0,
            'adjustment_minor' => -500,
            'reason' => 'Owner correction',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();
        $this->assertSame(2, DB::table('play_session_adjustments')->where('session_id', $session->id)->count());
    }

    public function test_non_manager_and_stale_requests_do_not_mutate(): void
    {
        [, , , $cashier, $session] = $this->fixture();
        $this->actingAs($cashier)->postJson($this->url($session), [
            'expected_lock_version' => 1,
            'adjustment_minor' => 100,
            'reason' => 'Cashier attempt',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->assertDatabaseCount('play_session_adjustments', 0);

        [, , $manager, , $session] = $this->fixture();
        $this->actingAs($manager)->postJson($this->url($session), [
            'expected_lock_version' => 9,
            'adjustment_minor' => 100,
            'reason' => 'Stale attempt',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(409);
        $this->assertDatabaseCount('play_session_adjustments', 0);
    }

    public function test_pending_payment_and_invalid_input_are_blocked(): void
    {
        [, , $manager, , $session] = $this->fixture(['status' => 'pending_payment']);
        $this->actingAs($manager)->postJson($this->url($session), [
            'expected_lock_version' => 1,
            'adjustment_minor' => 100,
            'reason' => 'Too late',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(409);

        [, , $manager, , $session] = $this->fixture();
        $this->actingAs($manager)->postJson($this->url($session), [
            'expected_lock_version' => 1,
            'extension_units' => 0,
            'adjustment_minor' => 0,
            'reason' => '',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertUnprocessable();
        $this->assertDatabaseCount('play_session_adjustments', 0);
    }

    public function test_foreign_tenant_session_is_hidden_without_mutation(): void
    {
        [, , $manager, , $session] = $this->fixture();
        [, , , , $foreignSession] = $this->fixture();
        $before = $this->adjustmentMutationSnapshot($foreignSession);

        $this->actingAs($manager)
            ->postJson($this->url($foreignSession), [
                'expected_lock_version' => $foreignSession->lock_version,
                'adjustment_minor' => 100,
                'reason' => 'Cross-tenant attempt',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertNotFound();

        $this->assertSame($before, $this->adjustmentMutationSnapshot($foreignSession));
    }

    public function test_unassigned_branch_session_is_hidden_without_mutation(): void
    {
        [$tenant, , $manager, , $session] = $this->fixture();
        DB::table('branch_user')
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $session->branch_id)
            ->where('user_id', $manager->id)
            ->delete();
        $before = $this->adjustmentMutationSnapshot($session);

        $this->actingAs($manager)
            ->postJson($this->url($session), [
                'expected_lock_version' => $session->lock_version,
                'adjustment_minor' => 100,
                'reason' => 'Unassigned branch attempt',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertNotFound();

        $this->assertSame($before, $this->adjustmentMutationSnapshot($session));
    }

    public function test_inactive_branch_session_is_hidden_without_mutation(): void
    {
        [, , $manager, , $session] = $this->fixture();
        DB::table('branches')->where('id', $session->branch_id)->update(['is_active' => false]);
        $before = $this->adjustmentMutationSnapshot($session);

        $this->actingAs($manager)
            ->postJson($this->url($session), [
                'expected_lock_version' => $session->lock_version,
                'adjustment_minor' => 100,
                'reason' => 'Inactive branch attempt',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertNotFound();

        $this->assertSame($before, $this->adjustmentMutationSnapshot($session));
    }

    public function test_checkout_freezes_the_deterministic_quote_with_prior_adjustments(): void
    {
        [$tenant, $owner, $manager, , $session] = $this->fixture();
        DB::table('guardian_child')->insert([
            'tenant_id' => $tenant->id,
            'guardian_id' => $session->guardian_id,
            'child_id' => $session->child_id,
            'relationship_type' => 'legal_guardian',
            'can_consent' => true,
            'can_check_out' => true,
            'is_primary' => true,
            'verification_method' => 'registered_phone_last_four',
            'verified_at' => now('UTC'),
            'verified_by_user_id' => $owner->id,
            'is_active' => true,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Carbon::setTestNow($session->started_at->addSeconds(4200));
        $this->actingAs($manager)->postJson($this->url($session), [
            'expected_lock_version' => 1,
            'extension_units' => 1,
            'adjustment_minor' => 1000,
            'reason' => 'Approved extra play time',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();

        $session->refresh();
        $this->actingAs($manager)
            ->postJson(route('sessions.checkout.prepare', ['session' => $session->id]), [
                'expected_lock_version' => $session->lock_version,
                'verification_method' => 'phone_last_four',
                'guardian_id' => $session->guardian_id,
                'phone_last_four' => substr((string) Guardian::query()->findOrFail($session->guardian_id)->phone_e164, -4),
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated()
            ->assertJsonPath('amount_due_minor', 23500);

        $snapshot = $session->fresh()->checkout_snapshot_json;
        $this->assertSame(1, $snapshot['extension_units']);
        $this->assertSame(1800, $snapshot['extension_seconds']);
        $this->assertSame(7500, $snapshot['extension_price_minor']);
        $this->assertSame(1000, $snapshot['adjustment_minor']);
        $this->assertSame(23500, $snapshot['total_minor']);
    }

    /** @return array{Tenant, User, User, User, PlaySession} */
    private function fixture(array $sessionOverrides = []): array
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $manager = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($manager, ['tenant_id' => $tenant->id, 'role' => 'branch_manager', 'is_active' => true]);
        $branch->users()->attach($cashier, ['tenant_id' => $tenant->id, 'role' => 'cashier', 'is_active' => true]);
        $rule = PricingRule::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $owner->id,
            'base_price_minor' => 15000,
            'overtime_price_minor' => 7500,
            'tax_rate_bps' => 0,
        ]);
        $guardian = Guardian::factory()->create(['tenant_id' => $tenant->id, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        $child = Child::factory()->create(['tenant_id' => $tenant->id, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        $type = TicketType::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'pricing_rule_id' => $rule->id,
            'code' => 'ADJ-'.Str::upper(Str::random(8)), 'name' => 'Adjustment test', 'price_minor' => 15000,
            'currency' => 'EGP', 'max_uses' => 1, 'status' => 'active', 'created_by_user_id' => $owner->id,
        ]);
        $ticket = Ticket::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'ticket_type_id' => $type->id,
            'guardian_id' => $guardian->id, 'child_id' => $child->id, 'service_date' => now()->toDateString(),
            'status' => 'consumed', 'code_hash' => hash('sha256', Str::random(32)), 'code_payload_encrypted' => 'payload',
            'display_code' => 'PN-'.Str::upper(Str::random(8)), 'price_minor' => 15000, 'currency' => 'EGP',
            'price_snapshot_json' => $this->snapshot($branch, $rule), 'issued_at' => now(), 'valid_from' => now(), 'valid_until' => now()->addDay(),
            'consumed_at' => now(), 'uses_count' => 1, 'max_uses' => 1, 'idempotency_key' => Str::uuid(),
            'issue_fingerprint' => hash('sha256', Str::random(32)), 'issued_by_user_id' => $owner->id, 'lock_version' => 1,
        ]);
        $startedAt = now('UTC')->subHour();
        $session = PlaySession::query()->create(array_merge([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'child_id' => $child->id, 'guardian_id' => $guardian->id,
            'ticket_id' => $ticket->id, 'pricing_rule_id' => $rule->id, 'status' => 'active', 'started_at' => $startedAt,
            'expected_end_at' => $startedAt->addHour(), 'pricing_snapshot_json' => $this->snapshot($branch, $rule),
            'created_by_user_id' => $owner->id, 'lock_version' => 1,
        ], $sessionOverrides));

        return [$tenant, $owner, $manager, $cashier, $session];
    }

    private function snapshot(Branch $branch, PricingRule $rule): array
    {
        return [
            'pricing_rule_id' => $rule->id, 'base_duration_seconds' => 3600, 'price_minor' => 15000,
            'grace_period_seconds' => 600, 'overtime_unit_seconds' => 1800, 'overtime_price_minor' => 7500,
            'tax_rate_bps' => 0, 'tax_mode' => 'exclusive', 'currency' => 'EGP', 'branch_timezone' => $branch->timezone,
        ];
    }

    private function url(PlaySession $session): string
    {
        return route('sessions.adjustments.store', ['session' => $session->id]);
    }

    /** @return array<string, mixed> */
    private function adjustmentMutationSnapshot(PlaySession $session): array
    {
        $fresh = $session->fresh();
        $sessionFields = [
            'tenant_id', 'branch_id', 'child_id', 'guardian_id', 'ticket_id', 'pricing_rule_id', 'status',
            'started_at', 'expected_end_at', 'ended_at', 'pricing_snapshot_json', 'lock_version',
            'checkout_idempotency_key', 'checkout_fingerprint', 'checkout_guardian_id', 'checkout_verification_method',
            'checkout_override_reason', 'checkout_verified_by_user_id', 'checkout_verified_at', 'checkout_prepared_at',
            'checkout_snapshot_json', 'checkout_amount_due_minor',
        ];
        $sessionSnapshot = [];
        foreach ($sessionFields as $field) {
            $sessionSnapshot[$field] = $fresh->getRawOriginal($field);
        }
        $quote = $fresh->checkout_snapshot_json;

        return [
            'branch' => DB::table('branches')->where('id', $fresh->branch_id)->get(['tenant_id', 'id', 'is_active', 'lock_version'])->map(static fn ($row): array => (array) $row)->all(),
            'session' => $sessionSnapshot,
            'adjustments' => DB::table('play_session_adjustments')
                ->where('tenant_id', $fresh->tenant_id)
                ->where('session_id', $fresh->id)
                ->orderBy('id')
                ->get()
                ->map(static fn ($row): array => (array) $row)
                ->all(),
            'commands' => DB::table('play_session_commands')
                ->where('tenant_id', $fresh->tenant_id)
                ->where('session_id', $fresh->id)
                ->orderBy('id')
                ->get()
                ->map(static fn ($row): array => (array) $row)
                ->all(),
            'events' => DB::table('play_session_events')
                ->where('tenant_id', $fresh->tenant_id)
                ->where('session_id', $fresh->id)
                ->orderBy('id')
                ->get()
                ->map(static fn ($row): array => (array) $row)
                ->all(),
            'audits' => DB::table('audit_logs')
                ->where('tenant_id', $fresh->tenant_id)
                ->orderBy('id')
                ->get()
                ->map(static fn ($row): array => (array) $row)
                ->all(),
            'payment' => [
                'amount_due_minor' => $fresh->checkout_amount_due_minor,
                'currency' => data_get($quote, 'currency'),
                'status' => $fresh->status === 'pending_payment' ? 'pending' : 'not_recorded',
            ],
            'totals' => [
                'subtotal_minor' => data_get($quote, 'subtotal_minor'),
                'tax_minor' => data_get($quote, 'tax_minor'),
                'total_minor' => data_get($quote, 'total_minor'),
            ],
        ];
    }
}
