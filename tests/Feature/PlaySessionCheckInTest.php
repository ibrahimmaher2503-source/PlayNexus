<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Child;
use App\Models\CustomRole;
use App\Models\Guardian;
use App\Models\PlaySession;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PlaySessionCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_owner_manager_and_reception_can_check_in_but_cashier_and_custom_roles_cannot(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Check-in branch');
        $rule = $this->rule($tenant, $branch, $owner, 'CHECK-IN-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'CHECK-IN-TYPE');
        $date = $this->openForToday($branch);

        $manager = $this->staff($tenant);
        $this->assign($tenant, $manager, $branch, 'branch_manager');
        $reception = $this->staff($tenant);
        $this->assign($tenant, $reception, $branch, 'reception_staff');
        $cashier = $this->staff($tenant);
        $this->assign($tenant, $cashier, $branch, 'cashier');
        $custom = $this->staff($tenant);
        $role = CustomRole::query()->create(['tenant_id' => $tenant->id, 'name' => 'Session viewer', 'code' => 'session_viewer']);
        $role->permissions()->create(['permission' => 'branches.view']);
        $this->assign($tenant, $custom, $branch, $role->code);

        foreach ([$owner, $manager, $reception] as $actor) {
            [$guardian, $child] = $this->family($tenant, $owner, 'Allowed '.(string) $actor->id);
            $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);

            $this->actingAs($actor)
                ->postJson(route('sessions.check-in'), $this->checkInPayload($branch, $ticket))
                ->assertCreated()
                ->assertJsonPath('status', 'active');
        }

        foreach ([$cashier, $custom] as $actor) {
            [$guardian, $child] = $this->family($tenant, $owner, 'Denied '.(string) $actor->id);
            $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);

            $this->actingAs($actor)
                ->postJson(route('sessions.check-in'), $this->checkInPayload($branch, $ticket))
                ->assertForbidden();
        }

        $this->assertSame(3, DB::table('play_sessions')->count());
        $this->assertSame(3, DB::table('play_session_events')->where('event_type', 'checked_in')->count());
    }

    public function test_check_in_consumes_ticket_locks_holder_and_freezes_utc_session_facts_atomically(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'FACTS-RULE', [
            'version' => 7,
            'base_duration_seconds' => 5400,
            'base_price_minor' => 12345,
        ]);
        $type = $this->type($tenant, $branch, $rule, $owner, 'FACTS-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner, 'Facts family');
        $date = $this->openForToday($branch);
        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);
        $issuedSnapshot = $ticket->price_snapshot_json;
        $startedAt = CarbonImmutable::now('UTC');

        $response = $this->actingAs($owner)
            ->postJson(route('sessions.check-in'), $this->checkInPayload($branch, $ticket))
            ->assertCreated()
            ->assertJsonStructure(['session_id', 'ticket_id', 'status', 'started_at', 'expected_end_at']);

        $session = DB::table('play_sessions')->where('id', $response->json('session_id'))->first();
        $this->assertNotNull($session);
        $this->assertSame($ticket->id, (int) $session->ticket_id);
        $this->assertSame($child->id, (int) $session->child_id);
        $this->assertSame($guardian->id, (int) $session->guardian_id);
        $this->assertSame('active', $session->status);
        $this->assertEqualsWithDelta($startedAt->timestamp, CarbonImmutable::parse($session->started_at, 'UTC')->timestamp, 1);
        $this->assertSame(
            CarbonImmutable::parse($session->started_at, 'UTC')->addSeconds(5400)->timestamp,
            CarbonImmutable::parse($session->expected_end_at, 'UTC')->timestamp,
        );
        $this->assertSame($issuedSnapshot, json_decode($session->pricing_snapshot_json, true));

        $ticket = $ticket->fresh();
        $this->assertSame(1, $ticket->uses_count);
        $this->assertSame(2, $ticket->lock_version);
        $this->assertNotNull($ticket->consumed_at);
        $this->assertNotNull($ticket->assignment_locked_at);
        $this->assertDatabaseHas('play_session_events', [
            'session_id' => $session->id,
            'event_type' => 'checked_in',
            'from_status' => null,
            'to_status' => 'active',
            'actor_user_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'session.checked_in',
            'subject_type' => 'play_session',
            'subject_id' => (string) $session->id,
        ]);
    }

    public function test_check_in_accepts_previously_accepted_validation_and_manual_code_replay_is_canonical(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'REPLAY-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'REPLAY-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner);
        $date = $this->openForToday($branch);
        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);

        $this->actingAs($owner)->postJson(route('tickets.scan'), [
            'branch_id' => $branch->id,
            'code' => $ticket->code_payload_encrypted,
            'idempotency_key' => Str::uuid()->toString(),
        ])->assertOk()->assertJsonPath('result', 'accepted');

        $key = Str::uuid()->toString();
        $first = $this->actingAs($owner)
            ->withHeader('Idempotency-Key', $key)
            ->postJson(route('sessions.check-in'), [
                'branch_id' => (string) $branch->id,
                'code' => strtolower($ticket->display_code),
            ])
            ->assertCreated();
        $sessionId = $first->json('session_id');

        $replay = $this->actingAs($owner)
            ->withHeader('Idempotency-Key', $key)
            ->postJson(route('sessions.check-in'), [
                'branch_id' => $branch->id,
                'code' => strtoupper($ticket->display_code),
                'idempotency_key' => $key,
            ])
            ->assertOk();

        $this->assertSame($sessionId, $replay->json('session_id'));
        $this->assertSame(1, DB::table('play_sessions')->count());
        $this->assertSame(1, DB::table('play_session_events')->where('event_type', 'checked_in')->count());
        $this->assertSame(1, $ticket->fresh()->uses_count);

        $this->actingAs($owner)
            ->withHeader('Idempotency-Key', $key)
            ->postJson(route('sessions.check-in'), [
                'branch_id' => $branch->id,
                'code' => 'PN-CHANGED1',
                'idempotency_key' => $key,
            ])
            ->assertConflict();
    }

    public function test_check_in_rechecks_scope_and_role_on_replay_and_hides_foreign_unassigned_or_inactive_branches(): void
    {
        [$tenant, $owner] = $this->owner();
        $assigned = $this->branch($tenant, 'Assigned branch');
        $unassigned = $this->branch($tenant, 'Unassigned branch');
        $inactive = $this->branch($tenant, 'Inactive branch');
        $inactive->update(['is_active' => false]);
        $manager = $this->staff($tenant);
        $this->assign($tenant, $manager, $assigned, 'branch_manager');
        $rule = $this->rule($tenant, $assigned, $owner, 'SCOPE-RULE');
        $type = $this->type($tenant, $assigned, $rule, $owner, 'SCOPE-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner);
        $date = $this->openForToday($assigned);
        $ticket = $this->issue($owner, $assigned, $type, $guardian, $child, $date);
        $payload = $this->checkInPayload($assigned, $ticket);

        $this->actingAs($manager)->postJson(route('sessions.check-in'), array_merge($payload, ['branch_id' => $unassigned->id]))->assertNotFound();
        $this->actingAs($manager)->postJson(route('sessions.check-in'), array_merge($payload, ['branch_id' => $inactive->id]))->assertNotFound();

        $foreignTenant = Tenant::factory()->create(['is_active' => true]);
        $foreignOwner = User::factory()->create(['tenant_id' => $foreignTenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $foreignTenant->id, 'user_id' => $foreignOwner->id, 'created_at' => now(), 'updated_at' => now()]);
        $foreignBranch = $this->branch($foreignTenant, 'Foreign branch');
        $foreignRule = $this->rule($foreignTenant, $foreignBranch, $foreignOwner, 'FOREIGN-RULE');
        $foreignType = $this->type($foreignTenant, $foreignBranch, $foreignRule, $foreignOwner, 'FOREIGN-TYPE');
        [$foreignGuardian, $foreignChild] = $this->family($foreignTenant, $foreignOwner);
        $foreignDate = $this->openForToday($foreignBranch);
        $foreignTicket = $this->issue($foreignOwner, $foreignBranch, $foreignType, $foreignGuardian, $foreignChild, $foreignDate);

        $this->actingAs($manager)->postJson(route('sessions.check-in'), [
            'branch_id' => $assigned->id,
            'code' => $foreignTicket->code_payload_encrypted,
            'idempotency_key' => Str::uuid()->toString(),
        ])->assertUnprocessable()->assertJsonPath('ticket_id', null)->assertJsonPath('session_id', null);

        $key = Str::uuid()->toString();
        $this->actingAs($manager)->withHeader('Idempotency-Key', $key)->postJson(route('sessions.check-in'), $payload)->assertCreated();
        DB::table('branch_user')->where('tenant_id', $tenant->id)->where('user_id', $manager->id)->where('branch_id', $assigned->id)->update(['is_active' => false]);
        $this->actingAs($manager)
            ->withHeader('Idempotency-Key', $key)
            ->postJson(route('sessions.check-in'), $payload)
            ->assertForbidden();
    }

    public function test_rejected_check_ins_are_privacy_safe_non_mutating_and_idempotent(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Open branch');
        $wrongBranch = $this->branch($tenant, 'Wrong branch');
        $rule = $this->rule($tenant, $branch, $owner, 'REJECT-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'REJECT-TYPE');
        $date = $this->openForToday($branch);
        [$guardian, $child] = $this->family($tenant, $owner, 'Rejected family');
        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);
        $cancelledTicket = $this->issue($owner, $branch, $type, $guardian, $child, $date);
        $before = $ticket->fresh()->only(['uses_count', 'consumed_at', 'assignment_locked_at']);

        $unknown = ['branch_id' => $branch->id, 'code' => 'PN-UNKNOWN', 'idempotency_key' => Str::uuid()->toString()];
        $this->actingAs($owner)->postJson(route('sessions.check-in'), $unknown)
            ->assertUnprocessable()->assertJsonPath('result', 'not_found')->assertJsonPath('ticket_id', null)->assertJsonPath('session_id', null);

        $wrong = ['branch_id' => $wrongBranch->id, 'code' => $ticket->code_payload_encrypted, 'idempotency_key' => Str::uuid()->toString()];
        $this->actingAs($owner)->postJson(route('sessions.check-in'), $wrong)
            ->assertUnprocessable()->assertJsonPath('result', 'wrong_branch')->assertJsonPath('ticket_id', null)->assertJsonPath('session_id', null);

        $closedNow = CarbonImmutable::now('Africa/Cairo')->setTime(18, 0);
        Carbon::setTestNow($closedNow);
        $closedKey = Str::uuid()->toString();
        $closed = ['branch_id' => $branch->id, 'code' => $ticket->display_code, 'idempotency_key' => $closedKey];
        $this->actingAs($owner)->postJson(route('sessions.check-in'), $closed)->assertUnprocessable()->assertJsonPath('result', 'expired');
        $this->actingAs($owner)->postJson(route('sessions.check-in'), $closed)->assertUnprocessable()->assertJsonPath('result', 'expired');

        Carbon::setTestNow(CarbonImmutable::now('Africa/Cairo')->setTime(10, 0));
        $this->consent($tenant, $guardian, $child, $owner, 'withdrawn', CarbonImmutable::now('UTC')->addMinute());
        $familyKey = Str::uuid()->toString();
        $familyRejected = ['branch_id' => $branch->id, 'code' => $ticket->display_code, 'idempotency_key' => $familyKey];
        $this->actingAs($owner)->postJson(route('sessions.check-in'), $familyRejected)->assertUnprocessable()->assertJsonPath('result', 'family_unavailable');
        $this->actingAs($owner)->postJson(route('sessions.check-in'), $familyRejected)->assertUnprocessable()->assertJsonPath('result', 'family_unavailable');

        [$revokedGuardian, $revokedChild] = $this->family($tenant, $owner, 'Revoked checkout family');
        $revokedTicket = $this->issue($owner, $branch, $type, $revokedGuardian, $revokedChild, $date);
        DB::table('guardian_child')->where('tenant_id', $tenant->id)->where('guardian_id', $revokedGuardian->id)->where('child_id', $revokedChild->id)->update(['can_check_out' => false]);
        $this->actingAs($owner)->postJson(route('sessions.check-in'), [
            'branch_id' => $branch->id,
            'code' => $revokedTicket->display_code,
            'idempotency_key' => Str::uuid()->toString(),
        ])->assertUnprocessable()->assertJsonPath('result', 'family_unavailable');

        [$emergencyGuardian, $emergencyChild] = $this->family($tenant, $owner, 'Missing emergency family');
        $emergencyTicket = $this->issue($owner, $branch, $type, $emergencyGuardian, $emergencyChild, $date);
        DB::table('children')->where('tenant_id', $tenant->id)->where('id', $emergencyChild->id)->update(['emergency_contact_phone_e164' => '']);
        $this->actingAs($owner)->postJson(route('sessions.check-in'), [
            'branch_id' => $branch->id,
            'code' => $emergencyTicket->display_code,
            'idempotency_key' => Str::uuid()->toString(),
        ])->assertUnprocessable()->assertJsonPath('result', 'family_unavailable');

        Carbon::setTestNow(CarbonImmutable::now('Africa/Cairo')->addDay()->setTime(10, 0));
        $this->actingAs($owner)->postJson(route('sessions.check-in'), [
            'branch_id' => $branch->id,
            'code' => $ticket->display_code,
            'idempotency_key' => Str::uuid()->toString(),
        ])->assertUnprocessable()->assertJsonPath('result', 'wrong_service_date');

        DB::table('tickets')->where('id', $cancelledTicket->id)->update(['status' => 'cancelled', 'cancelled_at' => now('UTC')]);
        $this->actingAs($owner)->postJson(route('sessions.check-in'), [
            'branch_id' => $branch->id,
            'code' => $cancelledTicket->display_code,
            'idempotency_key' => Str::uuid()->toString(),
        ])->assertUnprocessable()->assertJsonPath('result', 'cancelled');

        $this->assertSame($before, $ticket->fresh()->only(['uses_count', 'consumed_at', 'assignment_locked_at']));
        $this->assertSame(0, DB::table('play_sessions')->count());
        $this->assertSame(0, DB::table('play_session_events')->count());
        $this->assertSame(8, DB::table('ticket_scans')->where('scan_purpose', 'check_in')->count());
        $this->assertSame(1, DB::table('ticket_scans')->where('idempotency_key', $closedKey)->count());
        $this->assertSame(1, DB::table('ticket_scans')->where('idempotency_key', $familyKey)->count());
        $evidence = DB::table('ticket_scans')->where('scan_purpose', 'check_in')->get()->toJson();
        $this->assertStringNotContainsString($guardian->full_name, $evidence);
        $this->assertStringNotContainsString($guardian->phone_e164, $evidence);
        $this->assertStringNotContainsString($child->full_name, $evidence);
    }

    public function test_active_or_paused_child_conflict_and_capacity_denial_do_not_consume_ticket(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Capacity branch');
        $branch->update(['capacity' => 1]);
        $rule = $this->rule($tenant, $branch, $owner, 'CAPACITY-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'CAPACITY-TYPE');
        $date = $this->openForToday($branch);
        [$guardian, $child] = $this->family($tenant, $owner, 'Capacity first');
        $first = $this->issue($owner, $branch, $type, $guardian, $child, $date);
        $this->actingAs($owner)->postJson(route('sessions.check-in'), $this->checkInPayload($branch, $first))->assertCreated();

        [$otherGuardian, $otherChild] = $this->family($tenant, $owner, 'Capacity other');
        $other = $this->issue($owner, $branch, $type, $otherGuardian, $otherChild, $date);
        $this->actingAs($owner)
            ->postJson(route('sessions.check-in'), $this->checkInPayload($branch, $other))
            ->assertStatus(409)
            ->assertJsonPath('result', 'capacity_full');
        $this->assertSame(0, $other->fresh()->uses_count);
        $this->assertNull($other->fresh()->consumed_at);
        $this->assertNull($other->fresh()->assignment_locked_at);

        $sameChild = $this->issue($owner, $branch, $type, $guardian, $child, $date);
        $this->actingAs($owner)
            ->postJson(route('sessions.check-in'), $this->checkInPayload($branch, $sameChild))
            ->assertStatus(409)
            ->assertJsonPath('result', 'active_session_exists');
        $this->assertSame(0, $sameChild->fresh()->uses_count);

        DB::table('play_sessions')->where('ticket_id', $first->id)->update(['status' => 'paused']);
        $pausedTicket = $this->issue($owner, $branch, $type, $otherGuardian, $otherChild, $date);
        $this->actingAs($owner)
            ->postJson(route('sessions.check-in'), $this->checkInPayload($branch, $pausedTicket))
            ->assertStatus(409)
            ->assertJsonPath('result', 'capacity_full');
        $this->assertSame(1, DB::table('play_sessions')->count());
        $this->assertSame(0, $pausedTicket->fresh()->uses_count);
    }

    public function test_check_in_audit_failure_rolls_back_ticket_session_event_and_audit(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'ROLLBACK-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'ROLLBACK-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner);
        $date = $this->openForToday($branch);
        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);
        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert') && str_contains($query->sql, 'audit_logs')) {
                throw new RuntimeException('session audit failure');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($owner)
                ->postJson(route('sessions.check-in'), $this->checkInPayload($branch, $ticket));
            $this->fail('The injected audit failure should escape the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('session audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $ticket = $ticket->fresh();
        $this->assertSame(0, $ticket->uses_count);
        $this->assertNull($ticket->consumed_at);
        $this->assertNull($ticket->assignment_locked_at);
        $this->assertDatabaseCount('play_sessions', 0);
        $this->assertDatabaseCount('play_session_events', 0);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'ticket.issued')->count());
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'session.checked_in')->count());
    }

    public function test_cashier_can_view_scoped_masked_live_board_in_both_locales_without_check_in_form(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Board branch');
        $rule = $this->rule($tenant, $branch, $owner, 'BOARD-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'BOARD-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner, 'Board family');
        $date = $this->openForToday($branch);
        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);
        $this->actingAs($owner)->postJson(route('sessions.check-in'), $this->checkInPayload($branch, $ticket))->assertCreated();
        $cashier = $this->staff($tenant);
        $this->assign($tenant, $cashier, $branch, 'cashier');

        $this->actingAs($cashier)
            ->get(route('sessions.index', ['branch_id' => (string) $branch->id, 'q' => $child->full_name, 'status' => 'active']))
            ->assertOk()
            ->assertSee($child->full_name)
            ->assertDontSee($guardian->phone_e164)
            ->assertDontSee('data-pn-session-check-in', false);

        $this->withSession(['locale' => 'ar'])
            ->actingAs($cashier)
            ->get(route('sessions.index', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }

    public function test_play_session_foreign_branch_foreign_ticket_and_foreign_actor_foreign_keys_are_rejected(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $foreignTenant = Tenant::factory()->create(['is_active' => true]);
        $foreignOwner = User::factory()->create(['tenant_id' => $foreignTenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $foreignTenant->id, 'user_id' => $foreignOwner->id, 'created_at' => now(), 'updated_at' => now()]);
        $foreignBranch = $this->branch($foreignTenant, 'Foreign session branch');
        $rule = $this->rule($tenant, $branch, $owner, 'FK-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'FK-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner);
        $date = $this->openForToday($branch);
        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);

        try {
            DB::table('play_sessions')->insert([
                'tenant_id' => $tenant->id,
                'branch_id' => $foreignBranch->id,
                'child_id' => $child->id,
                'guardian_id' => $guardian->id,
                'ticket_id' => $ticket->id,
                'pricing_rule_id' => $rule->id,
                'status' => 'active',
                'started_at' => now('UTC'),
                'expected_end_at' => now('UTC')->addHour(),
                'pricing_snapshot_json' => '{}',
                'created_by_user_id' => $owner->id,
                'lock_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('A play session must not cross tenant branch scope.');
        } catch (QueryException) {
            $this->assertDatabaseCount('play_sessions', 0);
        }
    }

    public function test_live_board_estimate_is_read_only_exact_and_fails_closed_for_a_malformed_snapshot(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Estimate branch');
        $rule = $this->rule($tenant, $branch, $owner, 'ESTIMATE-RULE', [
            'base_duration_seconds' => 3600,
            'base_price_minor' => 15000,
            'grace_period_seconds' => 600,
            'overtime_unit_seconds' => 1800,
            'overtime_price_minor' => 7500,
            'tax_rate_bps' => 1400,
            'tax_mode' => 'exclusive',
            'currency' => 'EGP',
        ]);
        $type = $this->type($tenant, $branch, $rule, $owner, 'ESTIMATE-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner, 'Estimate family');
        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $this->openForToday($branch));

        $sessionId = $this->actingAs($owner)
            ->postJson(route('sessions.check-in'), $this->checkInPayload($branch, $ticket))
            ->assertCreated()
            ->json('session_id');
        $session = PlaySession::query()->findOrFail($sessionId);
        Carbon::setTestNow($session->started_at->addSeconds(4201));

        $state = static fn (): string => json_encode([
            'ticket' => DB::table('tickets')->where('id', $ticket->id)->first(),
            'session' => DB::table('play_sessions')->where('id', $sessionId)->first(),
            'events' => DB::table('play_session_events')->where('session_id', $sessionId)->count(),
            'scans' => DB::table('ticket_scans')->where('ticket_id', $ticket->id)->count(),
            'audits' => DB::table('audit_logs')->where('subject_type', 'play_session')->where('subject_id', (string) $sessionId)->count(),
        ], JSON_THROW_ON_ERROR);
        $before = $state();

        $this->actingAs($owner)
            ->get(route('sessions.index', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee('data-pn-live-estimate', false)
            ->assertSee(__('sessions.estimate.heading'))
            ->assertSee('256.50 EGP')
            ->assertSee('31.50 EGP')
            ->assertSee('14%')
            ->assertSee(__('sessions.estimate.not_final_disclaimer'));

        $this->assertSame($before, $state(), 'Reading the estimate must not mutate business records.');

        $session->forceFill(['pricing_snapshot_json' => ['price_minor' => 15000, 'branch_timezone' => 'Invalid/Timezone']])->save();
        $this->actingAs($owner)
            ->get(route('sessions.index', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertDontSee('data-pn-live-estimate', false);
    }

    private function owner(): array
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$tenant, $owner];
    }

    private function staff(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
    }

    private function branch(Tenant $tenant, string $name = 'Session branch'): Branch
    {
        return Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'timezone' => 'Africa/Cairo',
            'capacity' => 5,
            'is_active' => true,
        ]);
    }

    private function assign(Tenant $tenant, User $user, Branch $branch, string $role): void
    {
        $branch->users()->attach($user, [
            'tenant_id' => $tenant->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function rule(Tenant $tenant, Branch $branch, User $actor, string $code, array $overrides = []): PricingRule
    {
        return PricingRule::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $actor->id,
            'code' => $code,
            'name' => $code.' rule',
            'status' => 'active',
        ], $overrides));
    }

    private function type(Tenant $tenant, Branch $branch, PricingRule $rule, User $actor, string $code): TicketType
    {
        return TicketType::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'pricing_rule_id' => $rule->id,
            'code' => $code,
            'name' => $code.' type',
            'price_minor' => $rule->base_price_minor,
            'currency' => $rule->currency,
            'max_uses' => 1,
            'status' => 'active',
            'created_by_user_id' => $actor->id,
        ]);
    }

    private function family(Tenant $tenant, User $actor, string $label = 'Session family', array $overrides = []): array
    {
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => $label.' guardian',
            'phone_e164' => '+2010'.random_int(10000000, 99999999),
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
        $child = Child::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'full_name' => $label.' child',
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
            'emergency_contact_name' => $label.' emergency',
            'emergency_contact_phone_e164' => '+2011'.random_int(10000000, 99999999),
        ], array_intersect_key($overrides, array_flip(['emergency_contact_name', 'emergency_contact_phone_e164', 'status']))));
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
            'revoked_at' => null,
            'revoked_by_user_id' => null,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->consent($tenant, $guardian, $child, $actor, 'granted', now('UTC')->subSeconds(30));

        return [$guardian, $child];
    }

    private function consent(Tenant $tenant, Guardian $guardian, Child $child, User $actor, string $status, CarbonInterface $occurredAt): void
    {
        DB::table('family_consent_events')->insert([
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'consent_type' => 'child_data',
            'status' => $status,
            'notice_version' => '2026-09-12',
            'purpose_snapshot' => 'Safety and child-data processing',
            'data_categories_snapshot' => 'Child identity and emergency contact',
            'locale' => 'ar',
            'method' => 'staff_recorded',
            'actor_user_id' => $actor->id,
            'branch_id' => null,
            'request_id' => Str::uuid()->toString(),
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }

    private function openForToday(Branch $branch): string
    {
        $now = CarbonImmutable::now($branch->timezone)->startOfDay()->setTime(10, 0);
        Carbon::setTestNow($now);
        DB::table('branch_opening_hours')->updateOrInsert(
            ['tenant_id' => $branch->tenant_id, 'branch_id' => $branch->id, 'weekday' => $now->isoWeekday()],
            ['opens_at' => '09:00:00', 'closes_at' => '18:00:00', 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()],
        );

        return $now->toDateString();
    }

    private function issue(User $actor, Branch $branch, TicketType $type, Guardian $guardian, Child $child, string $date): Ticket
    {
        return Ticket::query()->findOrFail(
            $this->actingAs($actor)
                ->postJson(route('tickets.issue'), [
                    'branch_id' => $branch->id,
                    'ticket_type_id' => $type->id,
                    'guardian_id' => $guardian->id,
                    'child_id' => $child->id,
                    'service_date' => $date,
                    'idempotency_key' => Str::uuid()->toString(),
                ])
                ->assertCreated()
                ->json('ticket_id'),
        );
    }

    private function checkInPayload(Branch $branch, Ticket $ticket, array $overrides = []): array
    {
        return array_merge([
            'branch_id' => $branch->id,
            'code' => $ticket->code_payload_encrypted,
            'idempotency_key' => Str::uuid()->toString(),
        ], $overrides);
    }
}
