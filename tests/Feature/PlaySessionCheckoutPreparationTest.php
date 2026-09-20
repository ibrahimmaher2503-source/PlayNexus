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
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PlaySessionCheckoutPreparationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reception_verifies_guardian_and_freezes_the_exact_quote_in_the_cashier_queue(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        $reception = $this->staff($tenant, $branch, 'reception_staff');
        Carbon::setTestNow($session->started_at->addSeconds(4201));

        $response = $this->actingAs($reception)
            ->postJson($this->checkoutUrl($session), $this->checkoutPayload($session, $guardian, [
                'phone_last_four' => substr($guardian->phone_e164, -4),
            ]))
            ->assertCreated()
            ->assertJson([
                'session_id' => $session->id,
                'status' => 'pending_payment',
                'amount_due_minor' => 25650,
                'currency' => 'EGP',
                'created' => true,
            ]);

        $session = $session->fresh();
        $this->assertSame('pending_payment', $session->status);
        $this->assertSame(2, $session->lock_version);
        $this->assertSame($guardian->id, $session->checkout_guardian_id);
        $this->assertSame('phone_last_four', $session->checkout_verification_method);
        $this->assertSame(25650, $session->checkout_amount_due_minor);
        $this->assertEqualsCanonicalizing([
            'elapsed_seconds' => 4201,
            'included_seconds' => 4200,
            'overtime_seconds' => 1,
            'overtime_units' => 1,
            'base_price_minor' => 15000,
            'overtime_price_minor' => 7500,
            'subtotal_minor' => 22500,
            'net_minor' => 22500,
            'tax_minor' => 3150,
            'total_minor' => 25650,
            'tax_rate_bps' => 1400,
            'tax_mode' => 'exclusive',
            'currency' => 'EGP',
        ], $session->checkout_snapshot_json);
        $this->assertDatabaseHas('play_session_events', [
            'session_id' => $session->id,
            'event_type' => 'checkout_prepared',
            'from_status' => 'active',
            'to_status' => 'pending_payment',
            'reason_code' => 'guardian_verified',
            'actor_user_id' => $reception->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'session.checkout_prepared',
            'subject_type' => 'play_session',
            'subject_id' => (string) $session->id,
            'reason_code' => 'guardian_verified',
        ]);
        $this->assertSame(1, DB::table('play_session_events')->where('session_id', $session->id)->where('event_type', 'checkout_prepared')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'session.checkout_prepared')->count());
        $this->assertSame($session->id, $response->json('session_id'));
    }

    public function test_last_four_mismatch_rolls_back_the_preparation_without_leaking_or_mutating_state(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        $reception = $this->staff($tenant, $branch, 'reception_staff');
        $before = $session->fresh()->only(['status', 'lock_version', 'checkout_guardian_id', 'checkout_snapshot_json']);
        $eventCount = DB::table('play_session_events')->count();
        $auditCount = DB::table('audit_logs')->where('action', 'session.checkout_prepared')->count();

        $this->actingAs($reception)
            ->postJson($this->checkoutUrl($session), $this->checkoutPayload($session, $guardian, [
                'phone_last_four' => '0000',
            ]))
            ->assertStatus(422);

        $this->assertSame($before, $session->fresh()->only(['status', 'lock_version', 'checkout_guardian_id', 'checkout_snapshot_json']));
        $this->assertSame($eventCount, DB::table('play_session_events')->count());
        $this->assertSame($auditCount, DB::table('audit_logs')->where('action', 'session.checkout_prepared')->count());
    }

    public function test_sessions_board_exposes_due_indicator_and_guarded_lifecycle_actions_in_both_locales(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        Carbon::setTestNow($session->expected_end_at);

        $response = $this->actingAs($owner)
            ->get(route('sessions.index', ['branch_id' => $branch->id]));

        $response
            ->assertOk()
            ->assertSee('data-pn-due-indicator', false)
            ->assertSee(__('sessions.actions.extend_heading'))
            ->assertSee(route('sessions.extend', ['session' => $session->id]), false)
            ->assertSee(route('sessions.cancel', ['session' => $session->id]), false)
            ->assertSee(__('sessions.actions.cancel_consequence'))
            ->assertDontSee('data-pn-session-pause', false)
            ->assertDontSee('payment method', false);

        $html = (string) $response->getContent();
        $keys = [];
        foreach ([
            route('sessions.extend', ['session' => $session->id]),
            route('sessions.adjustments.store', ['session' => $session->id]),
            route('sessions.cancel', ['session' => $session->id]),
        ] as $action) {
            $matched = preg_match('/<form\\b[^>]*action="'.preg_quote($action, '/').'"[^>]*>.*?<input\\b[^>]*name="idempotency_key"[^>]*value="([^"]+)"/s', $html, $matches);
            $this->assertSame(1, $matched, 'Each lifecycle form must carry its own idempotency key.');
            $keys[] = $matches[1];
        }
        $this->assertSame(3, count(array_unique($keys)), 'Extend, adjustment, and cancel keys must be independent.');
        foreach ($keys as $key) {
            $this->assertSame(1, preg_match('/\\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\\z/i', $key));
        }

        $this->withSession(['locale' => 'ar'])
            ->actingAs($owner)
            ->get(route('sessions.index', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('sessions.actions.no_pause'));
    }

    public function test_pending_payment_board_exposes_frozen_invoice_without_payment_controls(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        Carbon::setTestNow($session->started_at->addSeconds(4201));

        $this->actingAs($owner)
            ->postJson($this->checkoutUrl($session), $this->checkoutPayload($session, $guardian))
            ->assertCreated();

        $this->actingAs($owner)
            ->get(route('sessions.index', ['branch_id' => $branch->id, 'status' => 'pending_payment']))
            ->assertOk()
            ->assertSee('data-pn-frozen-invoice', false)
            ->assertSee(__('sessions.checkout.frozen_invoice_heading'))
            ->assertSee(__('sessions.checkout.base_price'))
            ->assertSee(__('sessions.checkout.included_duration'))
            ->assertSee(__('sessions.checkout.overtime_charge'))
            ->assertSee(__('sessions.checkout.adjustment'))
            ->assertSee(__('sessions.checkout.verification_methods.phone_last_four'))
            ->assertSee(__('sessions.checkout.pending_handoff'))
            ->assertSee('256.50 EGP')
            ->assertDontSee('name="payment_method"', false)
            ->assertDontSee('name="amount_received"', false)
            ->assertDontSee(__('sessions.actions.extend_heading'));
    }

    public function test_live_estimate_uses_the_same_append_only_adjustments_as_checkout(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        DB::table('play_session_adjustments')->insert([
            'tenant_id' => $tenant->id,
            'session_id' => $session->id,
            'actor_user_id' => $owner->id,
            'extension_units' => 1,
            'adjustment_minor' => 0,
            'reason' => 'Approved extension',
            'expected_lock_version' => 1,
            'applied_lock_version' => 2,
            'request_id' => (string) Str::uuid(),
            'created_at' => now('UTC'),
        ]);
        Carbon::setTestNow($session->started_at->addSeconds(6000));

        $this->actingAs($owner)
            ->get(route('sessions.index', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertViewHas('sessions', function ($sessions): bool {
                $estimate = $sessions->getCollection()->first()?->getAttribute('live_estimate');

                $this->assertSame(1, $estimate['extension_units'] ?? null);
                $this->assertSame(0, $estimate['overtime_units'] ?? null);
                $this->assertSame(25650, $estimate['total_minor'] ?? null);

                return true;
            });
    }

    public function test_ineligible_or_foreign_guardian_is_rejected_without_changing_the_session(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        $reception = $this->staff($tenant, $branch, 'reception_staff');

        DB::table('guardian_child')
            ->where('tenant_id', $tenant->id)
            ->where('guardian_id', $guardian->id)
            ->where('child_id', $child->id)
            ->update(['can_check_out' => false]);
        $before = $this->checkoutMutationSnapshot($session);

        $this->actingAs($reception)
            ->postJson($this->checkoutUrl($session), $this->checkoutPayload($session, $guardian))
            ->assertNotFound();

        $foreignTenant = Tenant::factory()->create(['is_active' => true]);
        $foreignOwner = User::factory()->create(['tenant_id' => $foreignTenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert([
            'tenant_id' => $foreignTenant->id,
            'user_id' => $foreignOwner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignGuardian = Guardian::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'created_by_user_id' => $foreignOwner->id,
            'updated_by_user_id' => $foreignOwner->id,
        ]);

        $this->actingAs($reception)
            ->postJson($this->checkoutUrl($session), $this->checkoutPayload($session, $foreignGuardian))
            ->assertNotFound();

        $this->assertSame($before, $this->checkoutMutationSnapshot($session));
    }

    public function test_foreign_tenant_session_resource_is_hidden_without_mutation(): void
    {
        [$tenant] = $this->owner();
        $branch = $this->branch($tenant);
        $reception = $this->staff($tenant, $branch, 'reception_staff');

        [$foreignTenant, $foreignOwner] = $this->owner();
        $foreignBranch = $this->branch($foreignTenant);
        $foreignRule = $this->rule($foreignTenant, $foreignBranch, $foreignOwner);
        [$foreignGuardian, $foreignChild] = $this->family($foreignTenant, $foreignOwner);
        $foreignSession = $this->makeSession($foreignOwner, $foreignBranch, $foreignRule, $foreignGuardian, $foreignChild);
        $before = $this->checkoutMutationSnapshot($foreignSession);

        $this->actingAs($reception)
            ->postJson($this->checkoutUrl($foreignSession), $this->checkoutPayload($foreignSession, $foreignGuardian))
            ->assertNotFound();

        $this->assertSame($before, $this->checkoutMutationSnapshot($foreignSession));
    }

    public function test_unassigned_branch_session_is_hidden_without_mutation(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        $reception = $this->staff($tenant, $branch, 'reception_staff');
        DB::table('branch_user')
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branch->id)
            ->where('user_id', $reception->id)
            ->delete();
        $before = $this->checkoutMutationSnapshot($session);

        $this->actingAs($reception)
            ->postJson($this->checkoutUrl($session), $this->checkoutPayload($session, $guardian))
            ->assertNotFound();

        $this->assertSame($before, $this->checkoutMutationSnapshot($session));
    }

    public function test_inactive_branch_session_is_hidden_without_mutation(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        $reception = $this->staff($tenant, $branch, 'reception_staff');
        $branch->update(['is_active' => false]);
        $before = $this->checkoutMutationSnapshot($session);

        $this->actingAs($reception)
            ->postJson($this->checkoutUrl($session), $this->checkoutPayload($session, $guardian))
            ->assertNotFound();

        $this->assertSame($before, $this->checkoutMutationSnapshot($session));
    }

    public function test_cashier_cannot_prepare_a_checkout(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        $cashier = $this->staff($tenant, $branch, 'cashier');

        $this->actingAs($cashier)
            ->postJson($this->checkoutUrl($session), $this->checkoutPayload($session, $guardian))
            ->assertForbidden();

        $this->assertSame('active', $session->fresh()->status);
        $this->assertSame(0, DB::table('play_session_events')->where('event_type', 'checkout_prepared')->count());
    }

    public function test_manager_override_requires_a_reason_and_is_audited_when_accepted(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        $manager = $this->staff($tenant, $branch, 'branch_manager');

        $this->actingAs($manager)
            ->postJson($this->checkoutUrl($session), $this->checkoutPayload($session, $guardian, [
                'verification_method' => 'manager_override',
                'guardian_id' => null,
                'phone_last_four' => null,
                'override_reason' => 'Guardian identity confirmed in person',
            ]))
            ->assertCreated()
            ->assertJsonPath('status', 'pending_payment');

        $session = $session->fresh();
        $this->assertNull($session->checkout_guardian_id);
        $this->assertSame('manager_override', $session->checkout_verification_method);
        $this->assertSame('Guardian identity confirmed in person', $session->checkout_override_reason);
        $this->assertSame($manager->id, $session->checkout_verified_by_user_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'session.checkout_prepared',
            'reason_code' => 'guardian_manager_override',
            'actor_user_id' => $manager->id,
        ]);

        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $second = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        $manager = $this->staff($tenant, $branch, 'branch_manager');

        $this->actingAs($manager)
            ->postJson($this->checkoutUrl($second), $this->checkoutPayload($second, $guardian, [
                'verification_method' => 'manager_override',
                'guardian_id' => null,
                'phone_last_four' => null,
                'override_reason' => 'too short',
            ]))
            ->assertUnprocessable();
        $this->assertSame('active', $second->fresh()->status);
    }

    public function test_stale_lock_is_rejected_before_guardian_verification_or_quote_persistence(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        $reception = $this->staff($tenant, $branch, 'reception_staff');
        DB::table('play_sessions')->where('id', $session->id)->update(['lock_version' => 2]);

        $this->actingAs($reception)
            ->postJson($this->checkoutUrl($session), $this->checkoutPayload($session, $guardian))
            ->assertStatus(409);

        $this->assertSame('active', $session->fresh()->status);
        $this->assertSame(2, $session->fresh()->lock_version);
        $this->assertSame(0, DB::table('play_session_events')->where('event_type', 'checkout_prepared')->count());
    }

    public function test_same_idempotency_key_replays_the_preparation_but_changed_input_conflicts(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        $reception = $this->staff($tenant, $branch, 'reception_staff');
        $key = (string) Str::uuid();
        $payload = $this->checkoutPayload($session, $guardian, [
            'idempotency_key' => $key,
            'phone_last_four' => substr($guardian->phone_e164, -4),
        ]);

        $first = $this->actingAs($reception)
            ->postJson($this->checkoutUrl($session), $payload)
            ->assertCreated();
        $firstSnapshot = $session->fresh()->checkout_snapshot_json;

        $this->actingAs($reception)
            ->postJson($this->checkoutUrl($session), $payload)
            ->assertOk()
            ->assertJson([
                'session_id' => $first->json('session_id'),
                'status' => 'pending_payment',
                'created' => false,
            ]);

        $this->actingAs($reception)
            ->postJson($this->checkoutUrl($session), array_merge($payload, [
                'override_reason' => 'changed after submission',
            ]))
            ->assertStatus(409);

        $this->assertSame($firstSnapshot, $session->fresh()->checkout_snapshot_json);
        $this->assertSame(1, DB::table('play_session_events')->where('event_type', 'checkout_prepared')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'session.checkout_prepared')->count());
    }

    public function test_pending_payment_is_terminal_for_preparation_with_a_new_request_key(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        $reception = $this->staff($tenant, $branch, 'reception_staff');
        $payload = $this->checkoutPayload($session, $guardian, [
            'phone_last_four' => substr($guardian->phone_e164, -4),
        ]);
        $this->actingAs($reception)->postJson($this->checkoutUrl($session), $payload)->assertCreated();

        $this->actingAs($reception)
            ->postJson($this->checkoutUrl($session), $this->checkoutPayload($session, $guardian, [
                'phone_last_four' => substr($guardian->phone_e164, -4),
            ]))
            ->assertStatus(409);

        $this->assertSame('pending_payment', $session->fresh()->status);
        $this->assertSame(1, DB::table('play_session_events')->where('event_type', 'checkout_prepared')->count());
    }

    public function test_board_searches_ticket_qr_code_and_service_date_without_exposing_paused_state(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        $reception = $this->staff($tenant, $branch, 'reception_staff');
        $ticket = $session->ticket()->firstOrFail();

        $this->actingAs($reception)
            ->get(route('sessions.index', ['q' => $ticket->code_payload_encrypted, 'service_date' => $ticket->service_date->format('Y-m-d')]))
            ->assertOk()
            ->assertSee($child->full_name)
            ->assertSee($ticket->display_code)
            ->assertDontSee('value="paused"', false);

        $this->actingAs($reception)
            ->get(route('sessions.index', ['service_date' => $ticket->service_date->addDay()->format('Y-m-d')]))
            ->assertOk()
            ->assertDontSee($child->full_name);

        $this->actingAs($reception)
            ->getJson(route('sessions.index', ['status' => 'paused']))
            ->assertUnprocessable();
    }

    public function test_checkout_failure_during_audit_rolls_back_quote_verification_and_state(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        $reception = $this->staff($tenant, $branch, 'reception_staff');
        $before = $this->checkoutMutationSnapshot($session);
        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert') && str_contains($query->sql, 'audit_logs')) {
                throw new RuntimeException('checkout audit failure');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($reception)
                ->postJson($this->checkoutUrl($session), $this->checkoutPayload($session, $guardian));
            $this->fail('The injected checkout audit failure should escape the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('checkout audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $this->assertSame($before, $this->checkoutMutationSnapshot($session));
    }

    public function test_completed_history_keeps_release_verification_and_manager_override_visible(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner);
        [$guardian, $child] = $this->family($tenant, $owner);
        $session = $this->makeSession($owner, $branch, $rule, $guardian, $child);
        $manager = $this->staff($tenant, $branch, 'branch_manager');
        $reason = 'Identity confirmed against venue safety procedure';

        $this->actingAs($manager)
            ->postJson($this->checkoutUrl($session), $this->checkoutPayload($session, $guardian, [
                'verification_method' => 'manager_override',
                'guardian_id' => null,
                'phone_last_four' => null,
                'override_reason' => $reason,
            ]))
            ->assertCreated();
        $session->refresh()->forceFill(['status' => 'completed', 'ended_at' => now('UTC')])->save();

        $this->actingAs($manager)
            ->get(route('sessions.index', ['status' => 'completed']))
            ->assertOk()
            ->assertSee(__('sessions.checkout.release_history_heading'))
            ->assertSee(__('sessions.checkout.verification_methods.manager_override'))
            ->assertSee($reason)
            ->assertSee('data-pn-manager-override-history', false);
    }

    private function owner(): array
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $owner];
    }

    private function branch(Tenant $tenant): Branch
    {
        return Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'timezone' => 'Africa/Cairo',
            'capacity' => 5,
            'is_active' => true,
        ]);
    }

    private function staff(Tenant $tenant, Branch $branch, string $role): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($user, [
            'tenant_id' => $tenant->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return $user;
    }

    private function rule(Tenant $tenant, Branch $branch, User $owner): PricingRule
    {
        return PricingRule::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $owner->id,
            'code' => 'CHECKOUT-RULE-'.Str::upper(Str::random(6)),
            'base_duration_seconds' => 3600,
            'base_price_minor' => 15000,
            'grace_period_seconds' => 600,
            'overtime_unit_seconds' => 1800,
            'overtime_price_minor' => 7500,
            'tax_rate_bps' => 1400,
            'tax_mode' => 'exclusive',
            'currency' => 'EGP',
        ]);
    }

    private function family(Tenant $tenant, User $actor): array
    {
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
        $child = Child::factory()->create([
            'tenant_id' => $tenant->id,
            'emergency_contact_name' => 'Emergency contact',
            'emergency_contact_phone_e164' => '+201155555555',
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
        DB::table('guardian_child')->insert([
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'relationship_type' => 'legal_guardian',
            'can_consent' => true,
            'can_check_out' => true,
            'is_primary' => true,
            'verification_method' => 'registered_phone_last_four',
            'verified_at' => now('UTC')->subMinute(),
            'verified_by_user_id' => $actor->id,
            'is_active' => true,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('family_consent_events')->insert([
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'consent_type' => 'child_data',
            'status' => 'granted',
            'notice_version' => '2026-09-12',
            'purpose_snapshot' => 'Safety and child-data processing',
            'data_categories_snapshot' => 'Child identity and emergency contact',
            'locale' => 'ar',
            'method' => 'staff_recorded',
            'actor_user_id' => $actor->id,
            'branch_id' => null,
            'request_id' => Str::uuid()->toString(),
            'occurred_at' => now('UTC')->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$guardian, $child];
    }

    private function makeSession(User $owner, Branch $branch, PricingRule $rule, Guardian $guardian, Child $child): PlaySession
    {
        $now = CarbonImmutable::now('Africa/Cairo')->startOfDay()->setTime(10, 0);
        Carbon::setTestNow($now);
        DB::table('branch_opening_hours')->updateOrInsert(
            ['tenant_id' => $branch->tenant_id, 'branch_id' => $branch->id, 'weekday' => $now->isoWeekday()],
            [
                'opens_at' => '09:00:00',
                'closes_at' => '18:00:00',
                'is_closed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        $type = TicketType::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'pricing_rule_id' => $rule->id,
            'code' => 'CHECKOUT-TYPE-'.Str::upper(Str::random(6)),
            'name' => 'Checkout type',
            'price_minor' => $rule->base_price_minor,
            'currency' => $rule->currency,
            'max_uses' => 1,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);
        $ticket = Ticket::query()->findOrFail(
            $this->actingAs($owner)
                ->postJson(route('tickets.issue'), [
                    'branch_id' => $branch->id,
                    'ticket_type_id' => $type->id,
                    'guardian_id' => $guardian->id,
                    'child_id' => $child->id,
                    'service_date' => $now->toDateString(),
                    'idempotency_key' => Str::uuid()->toString(),
                ])
                ->assertCreated()
                ->json('ticket_id'),
        );
        $sessionId = $this->actingAs($owner)
            ->postJson(route('sessions.check-in'), [
                'branch_id' => $branch->id,
                'code' => $ticket->code_payload_encrypted,
                'idempotency_key' => Str::uuid()->toString(),
            ])
            ->assertCreated()
            ->json('session_id');

        return PlaySession::query()->findOrFail($sessionId);
    }

    private function checkoutUrl(PlaySession $session): string
    {
        return route('sessions.checkout.prepare', ['session' => $session->id]);
    }

    private function checkoutPayload(PlaySession $session, Guardian $guardian, array $overrides = []): array
    {
        return array_merge([
            'expected_lock_version' => $session->lock_version,
            'verification_method' => 'phone_last_four',
            'guardian_id' => $guardian->id,
            'phone_last_four' => substr($guardian->phone_e164, -4),
            'idempotency_key' => Str::uuid()->toString(),
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function checkoutMutationSnapshot(PlaySession $session): array
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
                ->where('action', '!=', 'security.request_denied')
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
