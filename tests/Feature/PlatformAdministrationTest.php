<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\TenantOwnerInvitation;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $platformRoutes = base_path('routes/platform.php');

        if (! Route::has('platform.login') && is_file($platformRoutes)) {
            require $platformRoutes;
        }
    }

    public function test_platform_login_regenerates_session_and_logout_invalidates_it(): void
    {
        [$admin] = $this->platformAdmin();

        $this->get(route('platform.login'))->assertOk();
        $beforeLogin = $this->app['session']->getId();

        $this->post(route('platform.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('platform.dashboard'));

        $afterLogin = $this->app['session']->getId();
        $this->assertNotSame($beforeLogin, $afterLogin);
        $this->assertAuthenticatedAs($admin);

        $this->post(route('platform.logout'))
            ->assertRedirect(route('platform.login'));

        $this->assertGuest();
        $this->assertNotSame($afterLogin, $this->app['session']->getId());
    }

    public function test_tenant_user_can_see_login_page_but_cannot_enter_platform_or_manage_tenants(): void
    {
        $tenant = $this->tenant(['name' => 'Tenant One']);
        $staff = $this->tenantUser($tenant, ['email' => 'tenant-user@example.test']);
        $foreign = $this->tenant(['internal_identifier' => 'foreign-target']);

        $loginPage = $this->actingAs($staff)->get(route('platform.login'));
        $this->assertContains($loginPage->status(), [200, 302]);

        $this->actingAs($staff)
            ->get(route('platform.tenants.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('platform.tenants.store'), $this->provisionPayload('tenant-denied'))
            ->assertForbidden();

        $response = $this->actingAs($staff)->patch(route('platform.tenants.status', $foreign), [
            'status' => 'suspended',
            'expected_status' => 'active',
            'reason_code' => 'access_review',
            'reason' => 'Security review requires this suspension.',
        ]);

        $response->assertForbidden();
        $this->assertStringNotContainsString($foreign->internal_identifier, $response->getContent());
    }

    public function test_tenant_user_gets_the_same_platform_status_response_for_existing_and_missing_tenant_ids(): void
    {
        $tenant = $this->tenant();
        $staff = $this->tenantUser($tenant, ['email' => 'tenant-enumeration@example.test']);
        $foreign = $this->tenant(['internal_identifier' => 'enumeration-target']);
        $payload = [
            'status' => 'suspended',
            'expected_status' => 'active',
            'reason_code' => 'access_review',
            'reason' => 'Security review requires this suspension.',
        ];

        $existing = $this->actingAs($staff)->patchJson(
            route('platform.tenants.status', $foreign),
            $payload,
        );
        $missing = $this->actingAs($staff)->patchJson(
            route('platform.tenants.status', (int) Tenant::query()->max('id') + 1),
            $payload,
        );

        $withoutRequestId = static function ($response): array {
            $json = $response->json();
            unset($json['request_id']);

            return $json;
        };

        $this->assertSame($existing->status(), $missing->status());
        $this->assertSame($withoutRequestId($existing), $withoutRequestId($missing));
        $this->assertSame(403, $existing->status());
    }

    public function test_tenant_credentials_receive_the_generic_platform_login_failure(): void
    {
        $tenant = $this->tenant();
        $staff = $this->tenantUser($tenant, ['email' => 'staff-platform-attempt@example.test']);

        $this->from(route('platform.login'))
            ->post(route('platform.login.store'), [
                'email' => $staff->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('platform.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_platform_admin_credentials_receive_the_generic_login_failure(): void
    {
        [$admin] = $this->platformAdmin(false);

        $this->from(route('platform.login'))
            ->post(route('platform.login.store'), [
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('platform.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_revoked_platform_admin_session_is_denied_on_the_next_request(): void
    {
        [$admin] = $this->platformAdmin();
        $this->loginAsPlatform($admin);

        DB::table('platform_admins')
            ->where('user_id', $admin->id)
            ->update(['is_active' => false]);

        $response = $this->get(route('platform.tenants.index'));

        $this->assertContains($response->status(), [302, 401, 403, 404]);
        $this->assertGuest();
    }

    public function test_active_platform_admin_sees_tenant_summary_without_staff_private_data(): void
    {
        [$admin] = $this->platformAdmin();
        $tenant = $this->tenant([
            'internal_identifier' => 'summary-venue',
            'name' => 'Summary Venue',
        ]);
        $owner = $this->tenantUser($tenant, [
            'name' => 'Private Owner Name',
            'email' => 'private-owner@example.test',
        ]);
        DB::table('tenant_owners')->insert($this->ownerRow($tenant, $owner));
        $staff = $this->tenantUser($tenant, [
            'name' => 'Private Staff Name',
            'email' => 'private-staff@example.test',
        ]);

        $this->loginAsPlatform($admin);

        $this->get(route('platform.tenants.index'))
            ->assertOk()
            ->assertSee('Summary Venue')
            ->assertSee('summary-venue')
            ->assertDontSee($owner->name)
            ->assertDontSee($owner->email)
            ->assertDontSee($staff->name)
            ->assertDontSee($staff->email);
    }

    public function test_tenant_detail_exposes_safe_administration_context_without_operational_pii(): void
    {
        [$admin] = $this->platformAdmin();
        $tenant = $this->tenant(['name' => 'Detail Venue', 'internal_identifier' => 'detail-venue']);
        $owner = $this->tenantUser($tenant, ['name' => 'Detail Owner', 'email' => 'detail-owner@example.test']);
        DB::table('tenant_owners')->insert($this->ownerRow($tenant, $owner));
        Branch::factory()->create(['tenant_id' => $tenant->id]);
        Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Protected Guardian Name',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        Child::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Protected Child Name',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);

        $this->loginAsPlatform($admin);
        $this->get(route('platform.tenants.show', $tenant))
            ->assertOk()
            ->assertSee('Detail Venue')
            ->assertSee('Detail Owner')
            ->assertSee('detail-owner@example.test')
            ->assertDontSee('Protected Guardian Name')
            ->assertDontSee('Protected Child Name');
    }

    public function test_platform_provision_creates_one_tenant_invited_owner_owner_link_and_audit(): void
    {
        [$admin] = $this->platformAdmin();
        $payload = $this->provisionPayload('provision-one');

        $this->loginAsPlatform($admin);
        $counts = $this->platformCounts();

        $this->post(route('platform.tenants.store'), $payload)
            ->assertRedirect(route('platform.tenants.index'));

        $tenant = Tenant::query()
            ->where('internal_identifier', strtoupper($payload['internal_identifier']))
            ->firstOrFail();
        $owner = User::query()->where('email', $payload['initial_owner_email'])->firstOrFail();

        $this->assertSame($counts['tenants'] + 1, Tenant::query()->count());
        $this->assertSame($counts['users'] + 1, User::query()->count());
        $this->assertSame($counts['tenant_owners'] + 1, DB::table('tenant_owners')->count());
        $this->assertSame($counts['platform_audits'] + 2, DB::table('platform_audit_logs')->count());
        $this->assertSame($tenant->id, $owner->tenant_id);
        $this->assertSame('invited', $owner->status);
        $this->assertDatabaseHas('tenant_owners', ['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

        $audit = DB::table('platform_audit_logs')
            ->where('target_tenant_id', $tenant->id)
            ->where('action', 'tenant.provisioned')
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame('tenant', $audit->subject_type);
        $this->assertSame((string) $tenant->id, $audit->subject_id);
        $this->assertSame('success', $audit->outcome);
        $this->assertNotEmpty($audit->request_id);
        $this->assertDatabaseHas('tenant_owner_invitations', [
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'destination_email' => $owner->email,
            'delivery_status' => 'manual_delivery_required',
        ]);
        $this->assertDatabaseHas('platform_audit_logs', [
            'target_tenant_id' => $tenant->id,
            'action' => 'tenant.owner_invitation.issued',
            'subject_id' => (string) $owner->id,
        ]);
    }

    public function test_initial_owner_invitation_is_hashed_single_use_and_activates_only_the_linked_owner(): void
    {
        [$admin] = $this->platformAdmin();
        $this->loginAsPlatform($admin);

        $response = $this->postJson(route('platform.tenants.store'), $this->provisionPayload('owner-acceptance'))
            ->assertCreated();
        $url = $response->json('owner_invitation_url');
        $token = Str::after((string) $url, '/owner-invitations/');
        $owner = User::query()->where('email', 'owner-owner-acceptance@example.test')->firstOrFail();
        $invitation = TenantOwnerInvitation::query()->where('owner_user_id', $owner->id)->firstOrFail();

        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame('invited', $owner->status);
        $this->post(route('platform.logout'))->assertRedirect(route('platform.login'));

        $this->post(route('owner-invitations.accept', ['token' => $token]), [
            'password' => 'safe-owner-password',
            'password_confirmation' => 'safe-owner-password',
        ])->assertRedirect(route('login'));

        $owner->refresh();
        $invitation->refresh();
        $this->assertSame('active', $owner->status);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertNotNull($invitation->accepted_at);
        $this->assertDatabaseHas('tenant_owners', ['tenant_id' => $owner->tenant_id, 'user_id' => $owner->id]);
        $this->assertDatabaseHas('platform_audit_logs', [
            'target_tenant_id' => $owner->tenant_id,
            'actor_user_id' => $owner->id,
            'action' => 'tenant.owner_invitation.accepted',
            'outcome' => 'success',
        ]);

        $this->post(route('owner-invitations.accept', ['token' => $token]), [
            'password' => 'another-safe-password',
            'password_confirmation' => 'another-safe-password',
        ])->assertRedirect(route('login'));
        $this->assertDatabaseHas('platform_audit_logs', [
            'target_tenant_id' => $owner->tenant_id,
            'action' => 'security.owner_invitation_denied',
            'outcome' => 'failure',
        ]);
    }

    public function test_owner_invitation_reissue_invalidates_the_prior_token_and_expired_token_fails_safely(): void
    {
        [$admin] = $this->platformAdmin();
        $this->loginAsPlatform($admin);
        $provisioned = $this->postJson(route('platform.tenants.store'), $this->provisionPayload('owner-reissue'))->assertCreated();
        $firstToken = Str::after((string) $provisioned->json('owner_invitation_url'), '/owner-invitations/');
        $tenant = Tenant::query()->where('internal_identifier', 'VENUE-OWNER-REISSUE')->firstOrFail();

        $this->postJson(route('platform.tenants.owner-invitation.reissue', $tenant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $reissued = $this->postJson(route('platform.tenants.owner-invitation.reissue', $tenant), [
            'reason' => 'The original secure handoff was not received.',
        ])->assertOk();
        $secondToken = Str::after((string) $reissued->json('owner_invitation_url'), '/owner-invitations/');
        $this->assertNotSame($firstToken, $secondToken);
        $this->assertDatabaseHas('platform_audit_logs', [
            'target_tenant_id' => $tenant->id,
            'action' => 'tenant.owner_invitation.reissued',
            'outcome' => 'success',
        ]);
        $this->post(route('platform.logout'))->assertRedirect(route('platform.login'));

        $this->post(route('owner-invitations.accept', ['token' => $firstToken]), [
            'password' => 'safe-owner-password',
            'password_confirmation' => 'safe-owner-password',
        ])->assertRedirect(route('login'));

        $invitation = TenantOwnerInvitation::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $invitation->forceFill(['expires_at' => now('UTC')->subSecond()])->save();
        $this->post(route('owner-invitations.accept', ['token' => $secondToken]), [
            'password' => 'safe-owner-password',
            'password_confirmation' => 'safe-owner-password',
        ])->assertRedirect(route('login'));
        $this->assertSame('invited', User::query()->findOrFail($invitation->owner_user_id)->status);
        $this->assertDatabaseHas('platform_audit_logs', [
            'target_tenant_id' => $tenant->id,
            'action' => 'security.owner_invitation_denied',
            'outcome' => 'failure',
        ]);
    }

    public function test_status_change_requires_a_human_reason_and_preserves_it_in_the_audit_snapshot(): void
    {
        [$admin] = $this->platformAdmin();
        $tenant = $this->tenant(['status' => 'active', 'is_active' => true]);
        $this->loginAsPlatform($admin);

        $this->from(route('platform.tenants.index'))->patch(route('platform.tenants.status', $tenant), [
            'status' => 'suspended',
            'expected_status' => 'active',
            'reason_code' => 'access_review',
        ])->assertRedirect(route('platform.tenants.index'))->assertSessionHasErrors('reason');
        $this->assertSame('active', $tenant->refresh()->status);

        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'suspended',
            'expected_status' => 'active',
            'reason' => 'Confirmed security concern requires suspension.',
        ])->assertRedirect(route('platform.tenants.index'));
        $audit = $this->latestTenantAudit($tenant);
        $this->assertSame('other', $audit->reason_code);
        $this->assertSame('Confirmed security concern requires suspension.', json_decode((string) $audit->after_json, true, flags: JSON_THROW_ON_ERROR)['reason']);
    }

    public function test_provision_ignores_or_rejects_a_client_tenant_scope(): void
    {
        [$admin] = $this->platformAdmin();
        $foreign = $this->tenant(['internal_identifier' => 'injected-scope']);
        $payload = $this->provisionPayload('scope-injection', [
            'tenant_id' => $foreign->id,
        ]);

        $this->loginAsPlatform($admin);
        $counts = $this->platformCounts();
        $response = $this->post(route('platform.tenants.store'), $payload);

        $this->assertContains($response->status(), [201, 302, 303, 422]);

        if ($response->status() === 422) {
            $this->assertSame($counts['tenants'], Tenant::query()->count());
            $this->assertSame($counts['users'], User::query()->count());
            $this->assertSame($counts['tenant_owners'], DB::table('tenant_owners')->count());
            $this->assertSame($counts['platform_audits'], DB::table('platform_audit_logs')->count());

            return;
        }

        $created = Tenant::query()
            ->where('internal_identifier', strtoupper($payload['internal_identifier']))
            ->firstOrFail();
        $owner = User::query()->where('email', $payload['initial_owner_email'])->firstOrFail();

        $this->assertNotSame($foreign->id, $created->id);
        $this->assertSame($created->id, $owner->tenant_id);
        $this->assertSame($counts['tenants'] + 1, Tenant::query()->count());
        $this->assertSame($counts['tenant_owners'] + 1, DB::table('tenant_owners')->count());
        $this->assertSame($counts['platform_audits'] + 2, DB::table('platform_audit_logs')->count());
    }

    public function test_internal_identifier_and_initial_owner_email_are_unique(): void
    {
        [$admin] = $this->platformAdmin();
        $existingTenant = $this->tenant(['internal_identifier' => 'ALREADY-USED']);
        User::factory()->create([
            'tenant_id' => $existingTenant->id,
            'email' => 'already-owner@example.test',
        ]);

        $this->loginAsPlatform($admin);
        $counts = $this->platformCounts();

        $this->from(route('platform.tenants.index'))
            ->post(route('platform.tenants.store'), $this->provisionPayload('unique-check', [
                'internal_identifier' => 'already-used',
                'initial_owner_email' => 'already-owner@example.test',
            ]))
            ->assertRedirect(route('platform.tenants.index'))
            ->assertSessionHasErrors(['internal_identifier', 'initial_owner_email']);

        $this->assertSame($counts['tenants'], Tenant::query()->count());
        $this->assertSame($counts['tenant_owners'], DB::table('tenant_owners')->count());
        $this->assertSame($counts['platform_audits'], DB::table('platform_audit_logs')->count());
    }

    public function test_same_idempotency_key_and_normalized_payload_replays_without_duplicates(): void
    {
        [$admin] = $this->platformAdmin();
        $raw = $this->provisionPayload('normalized-replay', [
            'internal_identifier' => '  normalized-venue  ',
            'name' => '  Normalized Venue  ',
            'initial_owner_name' => '  Initial Owner  ',
            'initial_owner_email' => 'Owner-Normalized@EXAMPLE.TEST',
        ]);
        $normalized = array_merge($raw, [
            'internal_identifier' => 'normalized-venue',
            'name' => 'Normalized Venue',
            'initial_owner_name' => 'Initial Owner',
            'initial_owner_email' => 'owner-normalized@example.test',
        ]);

        $this->loginAsPlatform($admin);
        $this->post(route('platform.tenants.store'), $raw)->assertRedirect(route('platform.tenants.index'));

        $tenant = Tenant::query()->where('internal_identifier', 'NORMALIZED-VENUE')->firstOrFail();
        $counts = $this->platformCounts();

        $this->post(route('platform.tenants.store'), $normalized)
            ->assertRedirect(route('platform.tenants.index'));

        $this->assertSame($tenant->id, Tenant::query()->where('internal_identifier', 'NORMALIZED-VENUE')->value('id'));
        $this->assertSame($counts['tenants'], Tenant::query()->count());
        $this->assertSame($counts['users'], User::query()->count());
        $this->assertSame($counts['tenant_owners'], DB::table('tenant_owners')->count());
        $this->assertSame($counts['platform_audits'], DB::table('platform_audit_logs')->count());
    }

    public function test_reusing_idempotency_key_with_a_different_payload_returns_conflict(): void
    {
        [$admin] = $this->platformAdmin();
        $payload = $this->provisionPayload('conflicting-replay');

        $this->loginAsPlatform($admin);
        $this->post(route('platform.tenants.store'), $payload)->assertRedirect(route('platform.tenants.index'));
        $counts = $this->platformCounts();

        $this->post(route('platform.tenants.store'), array_merge($payload, [
            'name' => 'Different Venue',
            'initial_owner_name' => 'Different Owner',
            'initial_owner_email' => 'different-owner@example.test',
        ]))->assertStatus(409);

        $this->assertSame($counts['tenants'], Tenant::query()->count());
        $this->assertSame($counts['users'], User::query()->count());
        $this->assertSame($counts['tenant_owners'], DB::table('tenant_owners')->count());
        $this->assertSame($counts['platform_audits'], DB::table('platform_audit_logs')->count());
    }

    public function test_provision_failure_rolls_back_tenant_owner_link_and_audit(): void
    {
        [$admin] = $this->platformAdmin();
        $payload = $this->provisionPayload('rollback-provision');
        $this->loginAsPlatform($admin);
        $counts = $this->platformCounts();

        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            $sql = strtolower(trim($query->sql));
            if (str_starts_with($sql, 'insert') && str_contains($sql, 'platform_audit_logs')) {
                throw new RuntimeException('platform audit failure');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->post(route('platform.tenants.store'), $payload);
            $this->fail('The platform audit failure should have escaped the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('platform audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $this->assertSame($counts['tenants'], Tenant::query()->count());
        $this->assertSame($counts['users'], User::query()->count());
        $this->assertSame($counts['tenant_owners'], DB::table('tenant_owners')->count());
        $this->assertDatabaseMissing('tenants', ['internal_identifier' => strtoupper($payload['internal_identifier'])]);
        $this->assertDatabaseMissing('users', ['email' => $payload['initial_owner_email']]);
    }

    public function test_status_change_rolls_back_tenant_and_auth_versions_when_audit_write_fails(): void
    {
        [$admin] = $this->platformAdmin();
        $tenant = $this->tenant(['status' => 'active', 'is_active' => true]);
        $staff = $this->tenantUser($tenant, ['email' => 'rollback-status-staff@example.test']);
        $staff->refresh();
        $lockVersion = (int) $tenant->lock_version;
        $authVersion = (int) $staff->auth_version;
        $auditCount = DB::table('platform_audit_logs')->where('target_tenant_id', $tenant->id)->count();

        $this->loginAsPlatform($admin);
        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            $sql = strtolower(trim($query->sql));
            if (str_starts_with($sql, 'insert') && str_contains($sql, 'platform_audit_logs')) {
                throw new RuntimeException('platform audit failure');
            }
        });

        try {
            $this->withoutExceptionHandling()->patch(route('platform.tenants.status', $tenant), [
                'status' => 'suspended',
                'expected_status' => 'active',
                'reason_code' => 'access_review',
                'reason' => 'Security review requires this suspension.',
            ]);
            $this->fail('The platform audit failure should have escaped the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('platform audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $tenant->refresh();
        $staff->refresh();
        $this->assertSame('active', $tenant->status);
        $this->assertTrue((bool) $tenant->is_active);
        $this->assertSame($lockVersion, (int) $tenant->lock_version);
        $this->assertSame($authVersion, (int) $staff->auth_version);
        $this->assertSame($auditCount, DB::table('platform_audit_logs')->where('target_tenant_id', $tenant->id)->count());
    }

    public function test_stale_tenant_status_returns_409_without_audit_or_version_change(): void
    {
        [$admin] = $this->platformAdmin();
        $tenant = $this->tenant(['status' => 'active', 'is_active' => true]);
        $version = (int) $tenant->lock_version;
        DB::table('tenants')->where('id', $tenant->id)->update([
            'status' => 'suspended',
            'is_active' => false,
        ]);

        $this->loginAsPlatform($admin);
        $counts = $this->platformCounts();
        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'active',
            'expected_status' => 'active',
            'reason_code' => 'correction',
            'reason' => 'Correct a stale administrative state.',
        ])->assertStatus(409);

        $current = Tenant::query()->findOrFail($tenant->id);
        $this->assertSame('suspended', $current->status);
        $this->assertFalse((bool) $current->is_active);
        $this->assertSame($version, (int) $current->lock_version);
        $this->assertSame($counts['platform_audits'], DB::table('platform_audit_logs')->count());
    }

    public function test_status_noop_does_not_create_audit_or_change_version(): void
    {
        [$admin] = $this->platformAdmin();
        $tenant = $this->tenant(['status' => 'active', 'is_active' => true]);
        $version = (int) $tenant->lock_version;

        $this->loginAsPlatform($admin);
        $counts = $this->platformCounts();
        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'active',
            'expected_status' => 'active',
            'reason_code' => 'correction',
            'reason' => 'No operation should be recorded.',
        ])->assertRedirect(route('platform.tenants.index'));

        $current = Tenant::query()->findOrFail($tenant->id);
        $this->assertSame('active', $current->status);
        $this->assertTrue((bool) $current->is_active);
        $this->assertSame($version, (int) $current->lock_version);
        $this->assertSame($counts['platform_audits'], DB::table('platform_audit_logs')->count());
    }

    public function test_suspend_and_activate_are_audited_with_actor_reason_and_before_after_state(): void
    {
        [$admin] = $this->platformAdmin();
        $tenant = $this->tenant(['status' => 'active', 'is_active' => true]);

        $this->loginAsPlatform($admin);
        $auditCount = DB::table('platform_audit_logs')->count();
        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'suspended',
            'expected_status' => 'active',
            'reason_code' => 'access_review',
            'reason' => 'Security review requires this suspension.',
        ])->assertRedirect(route('platform.tenants.index'));

        $tenant->refresh();
        $this->assertSame('suspended', $tenant->status);
        $this->assertFalse((bool) $tenant->is_active);

        $first = $this->latestTenantAudit($tenant);
        $this->assertSame($admin->id, $first->actor_user_id);
        $this->assertSame('access_review', $first->reason_code);
        $this->assertSame('tenant', $first->subject_type);
        $this->assertSame((string) $tenant->id, $first->subject_id);
        $this->assertSame('active', json_decode((string) $first->before_json, true, flags: JSON_THROW_ON_ERROR)['status']);
        $this->assertSame('suspended', json_decode((string) $first->after_json, true, flags: JSON_THROW_ON_ERROR)['status']);

        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'active',
            'expected_status' => 'suspended',
            'reason_code' => 'setup_change',
            'reason' => 'Tenant service is approved to resume.',
        ])->assertRedirect(route('platform.tenants.index'));

        $tenant->refresh();
        $this->assertSame('active', $tenant->status);
        $this->assertTrue((bool) $tenant->is_active);
        $second = $this->latestTenantAudit($tenant);
        $this->assertSame('setup_change', $second->reason_code);
        $this->assertSame('suspended', json_decode((string) $second->before_json, true, flags: JSON_THROW_ON_ERROR)['status']);
        $this->assertSame('active', json_decode((string) $second->after_json, true, flags: JSON_THROW_ON_ERROR)['status']);
        $this->assertSame($auditCount + 2, DB::table('platform_audit_logs')->count());
    }

    public function test_suspension_increments_auth_version_for_every_tenant_user_without_rewinding_on_reactivation(): void
    {
        [$admin] = $this->platformAdmin();
        $tenant = $this->tenant(['status' => 'active', 'is_active' => true]);
        $users = collect([
            $this->tenantUser($tenant, ['email' => 'suspension-active@example.test']),
            $this->tenantUser($tenant, ['email' => 'suspension-invited@example.test', 'status' => 'invited']),
        ]);
        $userIds = $users->pluck('id');
        $before = User::query()->whereIn('id', $userIds)->pluck('auth_version', 'id');

        $this->loginAsPlatform($admin);
        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'suspended',
            'expected_status' => 'active',
            'reason_code' => 'access_review',
            'reason' => 'Security review requires this suspension.',
        ])->assertRedirect(route('platform.tenants.index'));

        $afterSuspension = User::query()->whereIn('id', $userIds)->pluck('auth_version', 'id');
        foreach ($before as $userId => $authVersion) {
            $this->assertSame((int) $authVersion + 1, (int) $afterSuspension[$userId]);
        }

        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'active',
            'expected_status' => 'suspended',
            'reason_code' => 'setup_change',
            'reason' => 'Tenant service is approved to resume.',
        ])->assertRedirect(route('platform.tenants.index'));

        $this->assertSame($afterSuspension->all(), User::query()->whereIn('id', $userIds)->pluck('auth_version', 'id')->all());
    }

    public function test_suspended_tenant_denies_an_established_staff_session_on_next_protected_request(): void
    {
        $tenant = $this->tenant(['status' => 'active', 'is_active' => true]);
        $staff = $this->tenantUser($tenant, ['email' => 'established-staff@example.test']);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $staff->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => 'reception',
            'is_active' => true,
        ]);
        $staff->forceFill(['password' => Hash::make('password')])->save();

        $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->post(route('branch-context.store', $branch))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();

        DB::table('tenants')->where('id', $tenant->id)->update([
            'status' => 'suspended',
            'is_active' => false,
        ]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('branch_id');
        $this->assertGuest();
    }

    public function test_platform_login_renders_basic_ltr_and_rtl_html(): void
    {
        $this->get(route('platform.login'))
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false);

        $this->withSession(['locale' => 'ar'])
            ->get(route('platform.login'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false);
    }

    public function test_invalid_platform_login_preserves_the_selected_arabic_locale(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->from(route('platform.login'))
            ->post(route('platform.login.store'), [
                'email' => 'missing-platform@example.test',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('platform.login'))
            ->assertSessionHasErrors(['email' => 'بيانات الدخول غير صحيحة.'])
            ->assertSessionHas('locale', 'ar');

        $this->get(route('platform.login'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false);
    }

    public function test_platform_authentication_events_are_audited_without_credentials(): void
    {
        [$admin] = $this->platformAdmin();

        $this->from(route('platform.login'))->post(route('platform.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertRedirect(route('platform.login'));
        $this->post(route('platform.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('platform.dashboard'));
        $this->post(route('platform.logout'))->assertRedirect(route('platform.login'));

        $events = DB::table('platform_audit_logs')->where('actor_user_id', $admin->id)
            ->whereIn('action', ['auth.login_failed', 'auth.login_succeeded', 'auth.logout'])
            ->orderBy('id')->get();
        $this->assertSame(['auth.login_failed', 'auth.login_succeeded', 'auth.logout'], $events->pluck('action')->all());
        $this->assertFalse(str_contains($events->toJson(), 'wrong-password'));
        $this->assertFalse(str_contains($events->toJson(), $admin->email));
    }

    /** @return array{User} */
    private function platformAdmin(bool $active = true): array
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'email' => 'platform-'.Str::lower(Str::random(8)).'@example.test',
            'password' => Hash::make('password'),
        ]);
        $row = [
            'user_id' => $user->id,
            'is_active' => $active,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ];
        DB::table('platform_admins')->insert($row);

        return [$user];
    }

    private function loginAsPlatform(User $admin): void
    {
        $this->post(route('platform.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('platform.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->completeMfa($admin);
    }

    private function tenant(array $overrides = []): Tenant
    {
        return Tenant::factory()->create(array_merge([
            'internal_identifier' => 'tenant-'.Str::lower(Str::random(8)),
        ], $overrides))->refresh();
    }

    private function tenantUser(Tenant $tenant, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function provisionPayload(string $key, array $overrides = []): array
    {
        return array_merge([
            'internal_identifier' => 'venue-'.$key,
            'name' => 'Venue '.$key,
            'plan_reference' => 'starter',
            'initial_owner_name' => 'Initial Owner '.$key,
            'initial_owner_email' => 'owner-'.$key.'@example.test',
            'idempotency_key' => $key,
        ], $overrides);
    }

    /** @return array{tenants: int, users: int, tenant_owners: int, platform_audits: int} */
    private function platformCounts(): array
    {
        return [
            'tenants' => Tenant::query()->count(),
            'users' => User::query()->count(),
            'tenant_owners' => DB::table('tenant_owners')->count(),
            'platform_audits' => DB::table('platform_audit_logs')->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function ownerRow(Tenant $tenant, User $owner): array
    {
        return [
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ];
    }

    private function latestTenantAudit(Tenant $tenant): object
    {
        return DB::table('platform_audit_logs')
            ->where('target_tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }
}
