<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Child;
use App\Models\CustomRole;
use App\Models\Guardian;
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

class TicketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_owner_and_manager_can_create_types_but_reception_and_cashier_cannot(): void
    {
        [$tenant, $owner] = $this->owner();
        $ownerBranch = $this->branch($tenant, 'Owner branch');
        $ownerRule = $this->rule($tenant, $ownerBranch, $owner, 'OWNER-RULE');

        $this->actingAs($owner)
            ->postJson(route('ticket-types.store'), [
                'branch_id' => (string) $ownerBranch->id,
                'pricing_rule_id' => (string) $ownerRule->id,
                'code' => 'OWNER-TYPE',
                'name' => 'Owner type',
                'price_minor' => 1,
                'currency' => 'USD',
            ])
            ->assertCreated();

        $this->assertSame($ownerRule->base_price_minor, TicketType::query()->where('code', 'OWNER-TYPE')->value('price_minor'));

        $manager = $this->staff($tenant);
        $managerBranch = $this->branch($tenant, 'Manager branch');
        $this->assign($tenant, $manager, $managerBranch, 'branch_manager');
        $managerRule = $this->rule($tenant, $managerBranch, $owner, 'MANAGER-RULE');

        $this->actingAs($manager)
            ->postJson(route('ticket-types.store'), [
                'branch_id' => $managerBranch->id,
                'pricing_rule_id' => $managerRule->id,
                'code' => 'MANAGER-TYPE',
                'name' => 'Manager type',
            ])
            ->assertCreated();

        $this->assertSame($managerRule->base_price_minor, TicketType::query()->where('code', 'MANAGER-TYPE')->value('price_minor'));

        foreach (['reception_staff', 'reception', 'cashier'] as $role) {
            $staff = $this->staff($tenant);
            $this->assign($tenant, $staff, $ownerBranch, $role);

            $this->actingAs($staff)
                ->postJson(route('ticket-types.store'), [
                    'branch_id' => $ownerBranch->id,
                    'pricing_rule_id' => $ownerRule->id,
                    'code' => 'DENY-'.Str::upper(Str::substr($role, 0, 3)),
                    'name' => 'Denied type',
                ])
                ->assertForbidden();
        }

        $this->assertSame(2, TicketType::query()->count());
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'ticket.type.created')->count());
    }

    public function test_issue_uses_integer_rule_price_and_opaque_encrypted_qr_with_safe_audit(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'ISSUE-RULE', ['base_price_minor' => 12345, 'version' => 4]);
        $type = $this->type($tenant, $branch, $rule, $owner, 'DAY-123');
        [$guardian, $child] = $this->family($tenant, $owner);
        $date = $this->openForToday($branch);

        $response = $this->actingAs($owner)
            ->postJson(route('tickets.issue'), $this->issuePayload($branch, $type, $guardian, $child, $date, [
                'price_minor' => 1,
                'currency' => 'USD',
            ]))
            ->assertCreated()
            ->assertJsonStructure(['ticket_id', 'display_code', 'qr_payload', 'valid_from', 'valid_until', 'lock_version']);

        $ticket = Ticket::query()->findOrFail($response->json('ticket_id'));
        $qrPayload = $response->json('qr_payload');
        $rawEncrypted = DB::table('tickets')->where('id', $ticket->id)->value('code_payload_encrypted');
        $snapshot = $ticket->price_snapshot_json;

        $this->assertIsInt($ticket->price_minor);
        $this->assertSame(12345, $ticket->price_minor);
        $this->assertSame('EGP', $ticket->currency);
        $this->assertSame(12345, $snapshot['price_minor']);
        $this->assertSame(4, $snapshot['pricing_rule_version']);
        $this->assertSame('Africa/Cairo', $snapshot['branch_timezone']);
        $this->assertIsString($qrPayload);
        $this->assertStringStartsWith('pnx_', $qrPayload);
        $this->assertNotSame($qrPayload, $rawEncrypted);
        $this->assertSame(hash('sha256', $qrPayload), DB::table('tickets')->where('id', $ticket->id)->value('code_hash'));
        $this->assertMatchesRegularExpression('/^PN-[23456789A-Z]{8}$/', $ticket->display_code);
        $this->assertStringNotContainsString($guardian->full_name, $qrPayload);
        $this->assertStringNotContainsString($child->full_name, $qrPayload);

        $audit = DB::table('audit_logs')->where('action', 'ticket.issued')->sole();
        $this->assertStringNotContainsString($guardian->full_name, (string) $audit->after_json);
        $this->assertStringNotContainsString($guardian->phone_e164, (string) $audit->after_json);
        $this->assertStringNotContainsString($child->full_name, (string) $audit->after_json);
        $this->assertStringNotContainsString($qrPayload, (string) $audit->after_json);
    }

    public function test_issue_idempotency_canonicalizes_integer_ids_and_changed_input_conflicts(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'REPLAY-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'REPLAY-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner, 'First family');
        [$otherGuardian, $otherChild] = $this->family($tenant, $owner, 'Second family');
        $date = $this->openForToday($branch);
        $key = Str::uuid()->toString();

        $first = $this->issuePayload($branch, $type, $guardian, $child, $date, ['idempotency_key' => $key]);
        $this->actingAs($owner)->postJson(route('tickets.issue'), array_map('strval', $first))->assertCreated();
        $replay = $this->actingAs($owner)
            ->postJson(route('tickets.issue'), $first)
            ->assertOk()
            ->assertJsonPath('created', false);

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame($replay->json('ticket_id'), Ticket::query()->value('id'));

        $this->actingAs($owner)
            ->postJson(route('tickets.issue'), array_merge($first, [
                'guardian_id' => $otherGuardian->id,
                'child_id' => $otherChild->id,
            ]))
            ->assertConflict();

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'ticket.issued')->count());
    }

    public function test_issue_rejects_past_closed_and_local_close_boundary_dates(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'WINDOW-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'WINDOW-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner);
        $now = CarbonImmutable::now('Africa/Cairo')->startOfDay()->setTime(10, 0);
        Carbon::setTestNow($now);
        $today = $now->toDateString();
        $this->open($branch, $now->isoWeekday(), '09:00:00', '18:00:00');

        $this->actingAs($owner)
            ->postJson(route('tickets.issue'), $this->issuePayload($branch, $type, $guardian, $child, $now->subDay()->toDateString()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_date');

        $this->actingAs($owner)
            ->postJson(route('tickets.issue'), $this->issuePayload($branch, $type, $guardian, $child, $now->addDay()->toDateString()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_date');

        Carbon::setTestNow($now->setTime(18, 0));
        $this->actingAs($owner)
            ->postJson(route('tickets.issue'), $this->issuePayload($branch, $type, $guardian, $child, $today))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_date');

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_scan_acceptance_locks_assignment_but_rejected_unknown_and_wrong_branch_scans_do_not(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Scan branch');
        $wrongBranch = $this->branch($tenant, 'Wrong branch');
        $rule = $this->rule($tenant, $branch, $owner, 'SCAN-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'SCAN-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner);
        $date = $this->openForToday($branch);
        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);
        $qrPayload = $ticket->code_payload_encrypted;
        $branch->update(['timezone' => 'UTC']);

        $unknown = $this->actingAs($owner)
            ->postJson(route('tickets.scan'), [
                'branch_id' => $branch->id,
                'code' => 'unknown-code',
                'idempotency_key' => Str::uuid()->toString(),
            ])
            ->assertOk();
        $unknown->assertJsonPath('result', 'not_found')->assertJsonPath('ticket_id', null)->assertJsonPath('assignment_locked', false);

        $wrong = $this->actingAs($owner)
            ->postJson(route('tickets.scan'), [
                'branch_id' => $wrongBranch->id,
                'code' => $qrPayload,
                'idempotency_key' => Str::uuid()->toString(),
            ])
            ->assertOk();
        $wrong->assertJsonPath('result', 'wrong_branch')->assertJsonPath('ticket_id', null)->assertJsonPath('assignment_locked', false);

        $accepted = $this->actingAs($owner)
            ->postJson(route('tickets.scan'), [
                'branch_id' => $branch->id,
                'code' => $qrPayload,
                'idempotency_key' => Str::uuid()->toString(),
            ])
            ->assertOk();
        $accepted->assertJsonPath('result', 'accepted')->assertJsonPath('ticket_id', $ticket->id)->assertJsonPath('assignment_locked', true);

        $this->assertNotNull($ticket->fresh()->assignment_locked_at);
        $this->assertSame(0, Ticket::query()->where('id', $ticket->id)->value('uses_count'));
        $this->assertDatabaseHas('ticket_scans', ['ticket_id' => $ticket->id, 'result' => 'wrong_branch', 'branch_id' => $wrongBranch->id]);
        $this->assertDatabaseHas('ticket_scans', ['ticket_id' => null, 'result' => 'not_found', 'branch_id' => $branch->id]);
        $this->assertSame(3, DB::table('ticket_scans')->count());
    }

    public function test_scan_is_idempotent_and_close_boundary_is_half_open(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'SCAN-REPLAY-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'SCAN-REPLAY-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner);
        $now = CarbonImmutable::now('Africa/Cairo')->startOfDay()->setTime(10, 0);
        Carbon::setTestNow($now);
        $this->open($branch, $now->isoWeekday(), '09:00:00', '18:00:00');
        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $now->toDateString());
        $key = Str::uuid()->toString();
        $scan = ['branch_id' => (string) $branch->id, 'code' => $ticket->code_payload_encrypted, 'idempotency_key' => $key];

        $first = $this->actingAs($owner)->postJson(route('tickets.scan'), $scan)->assertOk();
        $this->actingAs($owner)->postJson(route('tickets.scan'), array_merge($scan, ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertJsonPath('result', 'accepted');
        $this->assertSame(1, DB::table('ticket_scans')->count());
        $this->assertSame(
            CarbonImmutable::parse($first->json('scanned_at'))->utc()->format('Y-m-d H:i:s'),
            DB::table('ticket_scans')->value('scanned_at'),
        );

        $this->actingAs($owner)
            ->postJson(route('tickets.scan'), array_merge($scan, ['code' => $ticket->display_code]))
            ->assertConflict();
        $this->assertSame(1, DB::table('ticket_scans')->count());

        $second = $this->issue($owner, $branch, $type, $guardian, $child, $now->toDateString());
        Carbon::setTestNow($now->setTime(18, 0));
        $this->actingAs($owner)
            ->postJson(route('tickets.scan'), [
                'branch_id' => $branch->id,
                'code' => $second->code_payload_encrypted,
                'idempotency_key' => Str::uuid()->toString(),
            ])
            ->assertOk()
            ->assertJsonPath('result', 'expired')
            ->assertJsonPath('ticket_id', $second->id)
            ->assertJsonPath('assignment_locked', false);
        $this->assertNull($second->fresh()->assignment_locked_at);
    }

    public function test_reassign_requires_reason_and_version_and_locks_after_first_accepted_scan(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'ASSIGN-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'ASSIGN-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner, 'Original family');
        [$otherGuardian, $otherChild] = $this->family($tenant, $owner, 'Replacement family');
        $date = $this->openForToday($branch);
        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);

        $this->actingAs($owner)
            ->patchJson(route('tickets.reassign', $ticket), [
                'guardian_id' => $otherGuardian->id,
                'child_id' => $otherChild->id,
                'expected_version' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->actingAs($owner)
            ->patchJson(route('tickets.reassign', $ticket), [
                'family_assignment' => $otherGuardian->id.':'.$otherChild->id,
                'reason' => 'guardian_request',
                'expected_version' => 99,
            ])
            ->assertConflict();

        $this->actingAs($owner)
            ->postJson(route('tickets.scan'), [
                'branch_id' => $branch->id,
                'code' => 'rejected-before-reassign',
                'idempotency_key' => Str::uuid()->toString(),
            ])
            ->assertJsonPath('assignment_locked', false);

        $this->actingAs($owner)
            ->patchJson(route('tickets.reassign', $ticket), [
                'family_assignment' => $otherGuardian->id.':'.$otherChild->id,
                'reason' => 'staff_correction',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('lock_version', 2);

        $ticket->refresh();
        $this->assertSame($otherGuardian->id, $ticket->guardian_id);
        $this->assertSame($otherChild->id, $ticket->child_id);
        $audit = DB::table('audit_logs')->where('action', 'ticket.assignment.changed')->sole();
        $this->assertStringContainsString((string) $guardian->id, (string) $audit->before_json);
        $this->assertStringContainsString((string) $otherChild->id, (string) $audit->after_json);
        $this->assertStringNotContainsString($otherGuardian->full_name, (string) $audit->after_json);

        $this->actingAs($owner)
            ->postJson(route('tickets.scan'), [
                'branch_id' => $branch->id,
                'code' => $ticket->code_payload_encrypted,
                'idempotency_key' => Str::uuid()->toString(),
            ])
            ->assertJsonPath('result', 'accepted');

        $this->actingAs($owner)
            ->patchJson(route('tickets.reassign', $ticket), [
                'guardian_id' => $guardian->id,
                'child_id' => $child->id,
                'reason' => 'staff_correction',
                'expected_version' => 2,
            ])
            ->assertConflict();
        $this->assertSame($otherChild->id, $ticket->fresh()->child_id);
    }

    public function test_issue_requires_verified_checkout_relationship_latest_consent_and_emergency_contact(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'FAMILY-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'FAMILY-TYPE');
        $date = $this->openForToday($branch);

        $cases = [
            ['verified_at' => null],
            ['can_check_out' => false],
            ['emergency_contact_name' => ''],
            ['emergency_contact_phone_e164' => ''],
        ];
        foreach ($cases as $index => $overrides) {
            [$guardian, $child] = $this->family($tenant, $owner, 'Invalid family '.$index, $overrides);
            $this->actingAs($owner)
                ->postJson(route('tickets.issue'), $this->issuePayload($branch, $type, $guardian, $child, $date))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('child_id');
        }

        [$withdrawnGuardian, $withdrawnChild] = $this->family($tenant, $owner, 'Withdrawn family');
        $this->consent($tenant, $withdrawnGuardian, $withdrawnChild, $owner, 'withdrawn', CarbonImmutable::now('UTC')->addMinute());
        $this->actingAs($owner)
            ->postJson(route('tickets.issue'), $this->issuePayload($branch, $type, $withdrawnGuardian, $withdrawnChild, $date))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('child_id');

        [$revokedGuardian, $revokedChild] = $this->family($tenant, $owner, 'Revoked family');
        DB::table('guardian_child')->where('tenant_id', $tenant->id)->where('guardian_id', $revokedGuardian->id)->where('child_id', $revokedChild->id)->update([
            'is_active' => false,
            'revoked_at' => now('UTC'),
            'revoked_by_user_id' => $owner->id,
        ]);
        $this->actingAs($owner)
            ->postJson(route('tickets.issue'), $this->issuePayload($branch, $type, $revokedGuardian, $revokedChild, $date))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('child_id');

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_owner_or_manager_can_cancel_only_unused_tickets_without_financial_refund_and_staff_cannot(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $manager = $this->staff($tenant);
        $this->assign($tenant, $manager, $branch, 'branch_manager');
        $rule = $this->rule($tenant, $branch, $owner, 'CANCEL-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'CANCEL-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner);
        $date = $this->openForToday($branch);
        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);

        $this->actingAs($manager)
            ->postJson(route('tickets.cancel', $ticket), ['reason' => 'customer_request', 'expected_version' => 1])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('financial_refund_processed', false);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'cancelled', 'cancellation_reason' => 'customer_request']);

        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);
        foreach (['reception_staff', 'reception', 'cashier'] as $role) {
            $staff = $this->staff($tenant);
            $this->assign($tenant, $staff, $branch, $role);
            $this->actingAs($staff)
                ->postJson(route('tickets.cancel', $ticket), ['reason' => 'staff_error', 'expected_version' => 1])
                ->assertForbidden();
        }

        $this->assertSame('issued', $ticket->fresh()->status);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'ticket.cancelled')->count());
    }

    public function test_reprint_is_immutable_and_always_audited(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'REPRINT-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'REPRINT-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner);
        $date = $this->openForToday($branch);
        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);
        $before = $ticket->fresh()->only(['status', 'guardian_id', 'child_id', 'price_minor', 'lock_version', 'code_hash']);
        $qr = $ticket->code_payload_encrypted;

        $this->actingAs($owner)
            ->postJson(route('tickets.reprint', $ticket), ['expected_version' => 1])
            ->assertOk()
            ->assertJsonPath('ticket_id', $ticket->id)
            ->assertJsonPath('qr_payload', $qr);
        $this->actingAs($owner)
            ->postJson(route('tickets.reprint', $ticket), ['expected_version' => 1])
            ->assertOk();
        $this->actingAs($owner)
            ->postJson(route('tickets.reprint', $ticket), ['expected_version' => 2])
            ->assertConflict();

        $this->assertSame($before, $ticket->fresh()->only(array_keys($before)));
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'ticket.reprinted')->count());
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'ticket.reprinted')->where('before_json', 'like', '%phone%')->count());
    }

    public function test_foreign_unassigned_and_inactive_ticket_scope_returns_not_found_without_leaks(): void
    {
        [$tenant, $owner] = $this->owner();
        $ownBranch = $this->branch($tenant, 'Own branch');
        $unassignedBranch = $this->branch($tenant, 'Unassigned branch');
        $ownRule = $this->rule($tenant, $ownBranch, $owner, 'SCOPE-OWN');
        $ownType = $this->type($tenant, $ownBranch, $ownRule, $owner, 'SCOPE-OWN-TYPE');
        [$ownGuardian, $ownChild] = $this->family($tenant, $owner, 'Own family');
        $ownDate = $this->openForToday($ownBranch);
        $manager = $this->staff($tenant);
        $this->assign($tenant, $manager, $ownBranch, 'branch_manager');
        $ownTicket = $this->issue($owner, $ownBranch, $ownType, $ownGuardian, $ownChild, $ownDate);

        $unassignedRule = $this->rule($tenant, $unassignedBranch, $owner, 'SCOPE-UNASSIGNED');
        $unassignedType = $this->type($tenant, $unassignedBranch, $unassignedRule, $owner, 'SCOPE-UNASSIGNED-TYPE');
        [$unassignedGuardian, $unassignedChild] = $this->family($tenant, $owner, 'Unassigned family');
        $unassignedDate = $this->openForToday($unassignedBranch);
        $unassignedTicket = $this->issue($owner, $unassignedBranch, $unassignedType, $unassignedGuardian, $unassignedChild, $unassignedDate);

        $this->actingAs($manager)
            ->postJson(route('tickets.reprint', $unassignedTicket), ['expected_version' => 1])
            ->assertNotFound();
        $unassignedBranch->update(['is_active' => false]);
        $this->actingAs($owner)
            ->postJson(route('tickets.reprint', $unassignedTicket), ['expected_version' => 1])
            ->assertNotFound();

        [$foreignTenant, $foreignOwner] = $this->owner();
        $foreignBranch = $this->branch($foreignTenant);
        $foreignRule = $this->rule($foreignTenant, $foreignBranch, $foreignOwner, 'SCOPE-FOREIGN');
        $foreignType = $this->type($foreignTenant, $foreignBranch, $foreignRule, $foreignOwner, 'SCOPE-FOREIGN-TYPE');
        [$foreignGuardian, $foreignChild] = $this->family($foreignTenant, $foreignOwner, 'Foreign private family');
        $foreignDate = $this->openForToday($foreignBranch);
        $foreignTicket = $this->issue($foreignOwner, $foreignBranch, $foreignType, $foreignGuardian, $foreignChild, $foreignDate);

        $this->actingAs($owner)
            ->postJson(route('tickets.reprint', $foreignTicket), ['expected_version' => 1])
            ->assertNotFound();
        $this->assertStringNotContainsString($foreignGuardian->full_name, (string) $ownTicket->fresh()->toJson());
    }

    public function test_hostile_custom_role_has_no_ticket_actions(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'CUSTOM-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'CUSTOM-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner);
        $date = $this->openForToday($branch);
        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);
        $custom = $this->staff($tenant);
        $role = CustomRole::create(['tenant_id' => $tenant->id, 'name' => 'Hostile viewer', 'code' => 'hostile_viewer']);
        $role->permissions()->create(['permission' => 'branches.view']);
        $this->assign($tenant, $custom, $branch, $role->code);

        $this->actingAs($custom)->getJson(route('tickets.index'))->assertForbidden();
        $this->actingAs($custom)->postJson(route('ticket-types.store'), [
            'branch_id' => $branch->id,
            'pricing_rule_id' => $rule->id,
            'code' => 'HOSTILE-TYPE',
            'name' => 'Hostile type',
        ])->assertForbidden();
        $this->actingAs($custom)->postJson(route('tickets.issue'), $this->issuePayload($branch, $type, $guardian, $child, $date))->assertForbidden();
        $this->actingAs($custom)->postJson(route('tickets.scan'), [
            'branch_id' => $branch->id,
            'code' => $ticket->code_payload_encrypted,
            'idempotency_key' => Str::uuid()->toString(),
        ])->assertForbidden();
        $this->actingAs($custom)->patchJson(route('tickets.reassign', $ticket), [
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'reason' => 'staff_correction',
            'expected_version' => 1,
        ])->assertForbidden();
        $this->actingAs($custom)->postJson(route('tickets.cancel', $ticket), ['reason' => 'staff_error', 'expected_version' => 1])->assertForbidden();
        $this->actingAs($custom)->postJson(route('tickets.reprint', $ticket), ['expected_version' => 1])->assertForbidden();
        $this->assertSame('issued', $ticket->fresh()->status);
        $this->assertSame(0, DB::table('ticket_scans')->count());
    }

    public function test_issue_audit_failure_rolls_back_ticket_and_audit(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'ISSUE-ROLLBACK-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'ISSUE-ROLLBACK-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner);
        $date = $this->openForToday($branch);
        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert') && str_contains($query->sql, 'audit_logs')) {
                throw new RuntimeException('audit failure');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($owner)
                ->postJson(route('tickets.issue'), $this->issuePayload($branch, $type, $guardian, $child, $date));
            $this->fail('The audit failure should escape the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_first_scan_audit_failure_rolls_back_lock_and_scan(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'SCAN-ROLLBACK-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'SCAN-ROLLBACK-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner);
        $date = $this->openForToday($branch);
        $ticket = $this->issue($owner, $branch, $type, $guardian, $child, $date);
        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert') && str_contains($query->sql, 'audit_logs')) {
                throw new RuntimeException('audit failure');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($owner)
                ->postJson(route('tickets.scan'), [
                    'branch_id' => $branch->id,
                    'code' => $ticket->code_payload_encrypted,
                    'idempotency_key' => Str::uuid()->toString(),
                ]);
            $this->fail('The audit failure should escape the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $this->assertNull($ticket->fresh()->assignment_locked_at);
        $this->assertSame(0, DB::table('ticket_scans')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'ticket.issued')->count());
    }

    public function test_reception_and_cashier_can_issue_and_cross_tenant_qr_is_not_found(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'STAFF-ISSUE-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'STAFF-ISSUE-TYPE');
        $date = $this->openForToday($branch);

        foreach (['reception_staff', 'cashier'] as $role) {
            $staff = $this->staff($tenant);
            $this->assign($tenant, $staff, $branch, $role);
            [$guardian, $child] = $this->family($tenant, $owner, $role.' family');

            $this->actingAs($staff)
                ->postJson(route('tickets.issue'), $this->issuePayload($branch, $type, $guardian, $child, $date))
                ->assertCreated();
        }

        [$foreignTenant, $foreignOwner] = $this->owner();
        $foreignBranch = $this->branch($foreignTenant);
        $foreignRule = $this->rule($foreignTenant, $foreignBranch, $foreignOwner, 'FOREIGN-QR-RULE');
        $foreignType = $this->type($foreignTenant, $foreignBranch, $foreignRule, $foreignOwner, 'FOREIGN-QR-TYPE');
        [$foreignGuardian, $foreignChild] = $this->family($foreignTenant, $foreignOwner, 'Foreign QR family');
        $foreignDate = $this->openForToday($foreignBranch);
        $foreignTicket = $this->issue($foreignOwner, $foreignBranch, $foreignType, $foreignGuardian, $foreignChild, $foreignDate);

        $this->actingAs($owner)
            ->postJson(route('tickets.scan'), [
                'branch_id' => $branch->id,
                'code' => $foreignTicket->code_payload_encrypted,
                'idempotency_key' => Str::uuid()->toString(),
            ])
            ->assertOk()
            ->assertJsonPath('result', 'not_found')
            ->assertJsonPath('ticket_id', null)
            ->assertJsonPath('assignment_locked', false);

        $this->assertSame(3, Ticket::query()->count());
        $this->assertSame(1, DB::table('ticket_scans')->count());
        $this->assertNull(DB::table('ticket_scans')->value('ticket_id'));
    }

    public function test_unassigned_and_inactive_branches_return_not_found_for_issue_type_and_scan(): void
    {
        [$tenant, $owner] = $this->owner();
        $managed = $this->branch($tenant, 'Managed branch');
        $unassigned = $this->branch($tenant, 'Unassigned branch');
        $inactive = $this->branch($tenant, 'Inactive branch');
        $manager = $this->staff($tenant);
        $this->assign($tenant, $manager, $managed, 'branch_manager');
        $rule = $this->rule($tenant, $managed, $owner, 'SCOPE-RULE');
        $type = $this->type($tenant, $managed, $rule, $owner, 'SCOPE-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner, 'Scope family');
        $date = $this->openForToday($managed);

        $this->actingAs($manager)->postJson(route('tickets.issue'), $this->issuePayload($unassigned, $type, $guardian, $child, $date))->assertNotFound();
        $this->actingAs($manager)->postJson(route('ticket-types.store'), [
            'branch_id' => $unassigned->id,
            'pricing_rule_id' => $rule->id,
            'code' => 'UNASSIGNED-TYPE',
            'name' => 'Unassigned type',
        ])->assertNotFound();
        $this->actingAs($manager)->postJson(route('tickets.scan'), [
            'branch_id' => $unassigned->id,
            'code' => 'unassigned-code',
            'idempotency_key' => Str::uuid()->toString(),
        ])->assertNotFound();

        $inactiveRule = $this->rule($tenant, $inactive, $owner, 'INACTIVE-RULE');
        $inactive->update(['is_active' => false]);
        $this->actingAs($owner)->postJson(route('ticket-types.store'), [
            'branch_id' => $inactive->id,
            'pricing_rule_id' => $inactiveRule->id,
            'code' => 'INACTIVE-TYPE',
            'name' => 'Inactive type',
        ])->assertNotFound();
        $this->actingAs($owner)->postJson(route('tickets.issue'), $this->issuePayload($inactive, $type, $guardian, $child, $date))->assertNotFound();
        $this->actingAs($owner)->postJson(route('tickets.scan'), [
            'branch_id' => $inactive->id,
            'code' => 'inactive-code',
            'idempotency_key' => Str::uuid()->toString(),
        ])->assertNotFound();

        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(0, TicketType::query()->whereIn('code', ['UNASSIGNED-TYPE', 'INACTIVE-TYPE'])->count());
        $this->assertSame(0, DB::table('ticket_scans')->count());
    }

    public function test_ticket_foreign_keys_reject_cross_branch_type_and_foreign_family_rows(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Primary branch');
        $otherBranch = $this->branch($tenant, 'Other branch');
        $rule = $this->rule($tenant, $branch, $owner, 'FK-RULE');
        $otherRule = $this->rule($tenant, $otherBranch, $owner, 'FK-OTHER-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'FK-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner, 'FK family');
        [$foreignTenant, $foreignOwner] = $this->owner();
        [$foreignGuardian, $foreignChild] = $this->family($foreignTenant, $foreignOwner, 'Foreign FK family');

        try {
            TicketType::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'pricing_rule_id' => $otherRule->id,
                'code' => 'CROSS-BRANCH-TYPE',
                'name' => 'Cross branch type',
                'price_minor' => 100,
                'currency' => 'EGP',
                'max_uses' => 1,
                'status' => 'active',
                'created_by_user_id' => $owner->id,
            ]);
            $this->fail('A ticket type must not link a pricing rule from another branch.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('ticket_types', ['code' => 'CROSS-BRANCH-TYPE']);
        }

        try {
            Ticket::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'ticket_type_id' => $type->id,
                'guardian_id' => $foreignGuardian->id,
                'child_id' => $foreignChild->id,
                'service_date' => CarbonImmutable::now('Africa/Cairo')->toDateString(),
                'status' => 'issued',
                'code_hash' => hash('sha256', 'foreign-secret'),
                'code_payload_encrypted' => 'foreign-secret',
                'display_code' => 'PN-FOREIGN1',
                'price_minor' => 100,
                'currency' => 'EGP',
                'price_snapshot_json' => ['price_minor' => 100, 'branch_timezone' => 'Africa/Cairo'],
                'issued_at' => now('UTC'),
                'valid_from' => now('UTC'),
                'valid_until' => now('UTC')->addHour(),
                'uses_count' => 0,
                'max_uses' => 1,
                'idempotency_key' => Str::uuid()->toString(),
                'issue_fingerprint' => hash('sha256', 'foreign-fingerprint'),
                'issued_by_user_id' => $owner->id,
                'lock_version' => 1,
            ]);
            $this->fail('A ticket must not link family records from another tenant.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('tickets', ['display_code' => 'PN-FOREIGN1']);
        }
    }

    public function test_active_type_keeps_issuing_from_retired_rule_with_original_price_snapshot(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $oldRule = $this->rule($tenant, $branch, $owner, 'VERSIONED-RULE', ['base_price_minor' => 11100]);
        $oldType = $this->type($tenant, $branch, $oldRule, $owner, 'VERSIONED-OLD-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner, 'Versioned family');
        $date = $this->openForToday($branch);

        $this->actingAs($owner)
            ->postJson(route('pricing.versions.store', $oldRule), [
                'name' => 'Version two',
                'base_duration_minutes' => 90,
                'base_price_egp' => '222.00',
                'overtime_price_egp' => '75.00',
                'expected_version' => 1,
            ])
            ->assertCreated();
        $newRule = PricingRule::query()->where('tenant_id', $tenant->id)->where('code', $oldRule->code)->where('status', 'active')->sole();

        $oldTicket = $this->issue($owner, $branch, $oldType, $guardian, $child, $date);
        $this->assertSame('retired', $oldRule->fresh()->status);
        $this->assertSame(11100, $oldTicket->price_minor);
        $this->assertSame(1, $oldTicket->price_snapshot_json['pricing_rule_version']);

        $newType = $this->type($tenant, $branch, $newRule, $owner, 'VERSIONED-NEW-TYPE');
        $newTicket = $this->issue($owner, $branch, $newType, $guardian, $child, $date);
        $this->assertSame(22200, $newTicket->price_minor);
        $this->assertSame(2, $newTicket->price_snapshot_json['pricing_rule_version']);
    }

    public function test_scan_rejects_withdrawn_or_revoked_family_without_locking_assignment(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'SCAN-FAMILY-RULE');
        $type = $this->type($tenant, $branch, $rule, $owner, 'SCAN-FAMILY-TYPE');
        $date = $this->openForToday($branch);

        [$withdrawnGuardian, $withdrawnChild] = $this->family($tenant, $owner, 'Scan withdrawn family');
        $withdrawnTicket = $this->issue($owner, $branch, $type, $withdrawnGuardian, $withdrawnChild, $date);
        $this->consent($tenant, $withdrawnGuardian, $withdrawnChild, $owner, 'withdrawn', CarbonImmutable::now('UTC')->addMinute());
        $this->actingAs($owner)
            ->postJson(route('tickets.scan'), [
                'branch_id' => $branch->id,
                'code' => $withdrawnTicket->code_payload_encrypted,
                'idempotency_key' => Str::uuid()->toString(),
            ])
            ->assertOk()
            ->assertJsonPath('result', 'family_unavailable')
            ->assertJsonPath('ticket_id', $withdrawnTicket->id)
            ->assertJsonPath('assignment_locked', false);

        [$revokedGuardian, $revokedChild] = $this->family($tenant, $owner, 'Scan revoked family');
        $revokedTicket = $this->issue($owner, $branch, $type, $revokedGuardian, $revokedChild, $date);
        DB::table('guardian_child')->where('tenant_id', $tenant->id)->where('guardian_id', $revokedGuardian->id)->where('child_id', $revokedChild->id)->update([
            'is_active' => false,
            'revoked_at' => now('UTC'),
            'revoked_by_user_id' => $owner->id,
        ]);
        $this->actingAs($owner)
            ->postJson(route('tickets.scan'), [
                'branch_id' => $branch->id,
                'code' => $revokedTicket->code_payload_encrypted,
                'idempotency_key' => Str::uuid()->toString(),
            ])
            ->assertOk()
            ->assertJsonPath('result', 'family_unavailable')
            ->assertJsonPath('ticket_id', $revokedTicket->id)
            ->assertJsonPath('assignment_locked', false);

        $this->assertNull($withdrawnTicket->fresh()->assignment_locked_at);
        $this->assertNull($revokedTicket->fresh()->assignment_locked_at);
        $this->assertSame(2, DB::table('ticket_scans')->where('result', 'family_unavailable')->count());
    }

    public function test_ticket_page_is_bilingual_scoped_and_masks_phone(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'UI-RULE');
        $this->type($tenant, $branch, $rule, $owner, 'UI-TYPE');
        [$guardian, $child] = $this->family($tenant, $owner, 'Visible family');
        $foreignTenant = Tenant::factory()->create();
        $foreignOwner = $this->staff($foreignTenant);
        DB::table('tenant_owners')->insert(['tenant_id' => $foreignTenant->id, 'user_id' => $foreignOwner->id, 'created_at' => now(), 'updated_at' => now()]);
        [$foreignGuardian] = $this->family($foreignTenant, $foreignOwner, 'Foreign secret family');

        $this->actingAs($owner)->get(route('tickets.index', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee($child->full_name)
            ->assertSee($guardian->maskedPhone())
            ->assertDontSee($guardian->phone_e164)
            ->assertDontSee($foreignGuardian->full_name);
        $this->withSession(['locale' => 'ar'])
            ->actingAs($owner)
            ->get(route('tickets.index', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee('dir="rtl"', false);
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

    private function branch(Tenant $tenant, string $name = 'Ticket branch'): Branch
    {
        return Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'timezone' => 'Africa/Cairo',
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

    private function family(Tenant $tenant, User $actor, string $label = 'Ticket family', array $overrides = []): array
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

        $relationship = array_merge([
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
        ], array_diff_key($overrides, array_flip(['emergency_contact_name', 'emergency_contact_phone_e164', 'status'])));
        DB::table('guardian_child')->insert($relationship);
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
        $this->open($branch, $now->isoWeekday(), '09:00:00', '18:00:00');

        return $now->toDateString();
    }

    private function open(Branch $branch, int $weekday, string $opensAt, string $closesAt): void
    {
        DB::table('branch_opening_hours')->updateOrInsert(
            ['tenant_id' => $branch->tenant_id, 'branch_id' => $branch->id, 'weekday' => $weekday],
            ['opens_at' => $opensAt, 'closes_at' => $closesAt, 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    private function issue(User $actor, Branch $branch, TicketType $type, Guardian $guardian, Child $child, string $date): Ticket
    {
        return Ticket::query()->findOrFail(
            $this->actingAs($actor)
                ->postJson(route('tickets.issue'), $this->issuePayload($branch, $type, $guardian, $child, $date))
                ->assertCreated()
                ->json('ticket_id'),
        );
    }

    private function issuePayload(Branch $branch, TicketType $type, Guardian $guardian, Child $child, string $date, array $overrides = []): array
    {
        return array_merge([
            'branch_id' => $branch->id,
            'ticket_type_id' => $type->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'service_date' => $date,
            'idempotency_key' => Str::uuid()->toString(),
        ], $overrides);
    }
}
