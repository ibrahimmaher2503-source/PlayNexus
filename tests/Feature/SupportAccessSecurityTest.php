<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Guardian;
use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_create_a_password_reauthenticated_time_bound_read_only_grant_and_audit_it(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::factory()->create(['name' => 'Support Tenant', 'internal_identifier' => 'SUPPORT-TENANT']);
        $this->loginAsPlatform($admin);

        $this->post(route('platform.support-access.store'), $this->grantPayload($tenant, $admin))
            ->assertRedirect();

        $grant = SupportAccessGrant::query()->sole();
        $this->assertSame($tenant->id, $grant->tenant_id);
        $this->assertSame($admin->id, $grant->requested_by_user_id);
        $this->assertSame('tenant_administration_read', $grant->scope);
        $this->assertSame('Investigate reported account configuration issue.', $grant->reason);
        $this->assertSame('SUP-1001', $grant->support_ticket);
        $this->assertTrue($grant->expires_at->isAfter($grant->granted_at));
        $this->assertTrue($grant->expires_at->lessThanOrEqualTo($grant->granted_at->addMinutes(60)));
        $this->assertDatabaseHas('platform_audit_logs', [
            'actor_user_id' => $admin->id,
            'target_tenant_id' => $tenant->id,
            'action' => 'support_access.granted',
            'subject_type' => 'support_access_grant',
            'subject_id' => (string) $grant->id,
            'outcome' => 'success',
        ]);
    }

    public function test_support_grant_rejects_wrong_password_invalid_scope_and_duration_above_the_approved_cap_without_writes(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::factory()->create();
        $this->loginAsPlatform($admin);

        $this->from(route('platform.support-access.index'))
            ->post(route('platform.support-access.store'), $this->grantPayload($tenant, $admin, [
                'current_password' => 'incorrect-password',
                'scope' => 'all_tenant_records',
                'duration_minutes' => 61,
            ]))
            ->assertRedirect(route('platform.support-access.index'))
            ->assertSessionHasErrors(['scope', 'duration_minutes']);
        $this->assertDatabaseCount('support_access_grants', 0);

        $this->from(route('platform.support-access.index'))
            ->post(route('platform.support-access.store'), $this->grantPayload($tenant, $admin, ['current_password' => 'incorrect-password']))
            ->assertRedirect(route('platform.support-access.index'))
            ->assertSessionHasErrors('current_password');
        $this->assertDatabaseCount('support_access_grants', 0);
    }

    public function test_expired_or_revoked_grants_deny_the_read_only_surface_and_record_the_denial(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::factory()->create();
        $grant = $this->grant($tenant, $admin, ['expires_at' => now('UTC')->subSecond()]);
        $this->loginAsPlatform($admin);

        $this->get(route('platform.support-access.show', $grant))->assertForbidden();
        $this->assertDatabaseHas('platform_audit_logs', [
            'target_tenant_id' => $tenant->id,
            'action' => 'support_access.denied',
            'subject_id' => (string) $grant->id,
            'outcome' => 'failure',
            'reason_code' => 'support_access_expired',
        ]);

        $active = $this->grant($tenant, $admin);
        $this->patch(route('platform.support-access.revoke', $active), [
            'reason' => 'The reported issue has been resolved safely.',
            'current_password' => 'password',
        ])->assertRedirect(route('platform.support-access.index'));
        $this->assertNotNull($active->refresh()->revoked_at);
        $this->assertSame($admin->id, $active->revoked_by_user_id);
        $this->assertDatabaseHas('platform_audit_logs', [
            'target_tenant_id' => $tenant->id,
            'action' => 'support_access.revoked',
            'subject_id' => (string) $active->id,
            'outcome' => 'success',
        ]);

        $this->get(route('platform.support-access.show', $active))->assertForbidden();
        $this->assertDatabaseHas('platform_audit_logs', [
            'target_tenant_id' => $tenant->id,
            'action' => 'support_access.denied',
            'subject_id' => (string) $active->id,
            'reason_code' => 'support_access_revoked',
        ]);
    }

    public function test_support_grant_never_impersonates_a_tenant_or_opens_operational_routes(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::factory()->create();
        $this->grant($tenant, $admin);
        $this->loginAsPlatform($admin);

        $this->get(route('platform.support-access.show', SupportAccessGrant::query()->sole()))->assertOk();
        $this->get('/families')->assertNotFound();
    }

    public function test_branch_configuration_scope_discloses_only_the_allowlisted_branch_facts(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Scoped configuration branch',
            'code' => 'SUPPORT-CONFIG',
            'capacity' => 42,
        ]);
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Must not appear in support',
        ]);
        $grant = $this->grant($tenant, $admin, ['scope' => 'branch_configuration_read']);
        $this->loginAsPlatform($admin);

        $this->get(route('platform.support-access.show', $grant))
            ->assertOk()
            ->assertSee($branch->name)
            ->assertSee($branch->code)
            ->assertSee('42')
            ->assertDontSee($guardian->full_name);
        $this->assertDatabaseHas('platform_audit_logs', [
            'target_tenant_id' => $tenant->id,
            'action' => 'support_access.viewed',
            'subject_id' => (string) $grant->id,
            'outcome' => 'success',
        ]);
    }

    public function test_only_the_target_tenant_owner_can_view_that_tenants_support_access_history(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
        $grant = $this->grant($tenant, $admin);

        $this->actingAs($owner)->get(route('tenant.support-access.index'))
            ->assertOk()
            ->assertSee($grant->reason)
            ->assertSee($grant->support_ticket);
        $this->actingAs($staff)->get(route('tenant.support-access.index'))->assertForbidden();
    }

    public function test_tenant_user_cannot_create_or_view_platform_support_access_for_any_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $target = Tenant::factory()->create();

        $this->actingAs($staff)->post(route('platform.support-access.store'), $this->grantPayload($target, $staff))
            ->assertForbidden();
        $this->actingAs($staff)->get(route('platform.support-access.index'))->assertForbidden();
        $this->assertDatabaseCount('support_access_grants', 0);
    }

    public function test_platform_suspension_revokes_an_established_tenant_session_and_reactivation_does_not_unsuspend_staff(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::factory()->create(['is_active' => true, 'status' => 'active']);
        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $individuallySuspended = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'suspended']);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $staff->branches()->attach($branch, ['tenant_id' => $tenant->id, 'role' => 'reception', 'is_active' => true]);
        $establishedVersion = (int) $staff->fresh()->auth_version;

        $this->loginAsPlatform($admin);
        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'suspended', 'expected_status' => 'active', 'reason_code' => 'access_review',
            'reason' => 'Security review requires this suspension.',
        ])->assertRedirect(route('platform.tenants.index'));
        $this->assertSame($establishedVersion + 1, (int) $staff->fresh()->auth_version);

        $this->actingAs($staff)->withSession(['auth_version' => $establishedVersion, 'branch_id' => $branch->id])
            ->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_user_id' => $staff->id,
            'action' => 'auth.session_revoked',
            'reason_code' => 'user_status_or_auth_version_changed',
        ]);

        $this->loginAsPlatform($admin);
        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'active', 'expected_status' => 'suspended', 'reason_code' => 'setup_change',
            'reason' => 'The administrative suspension has been resolved.',
        ])->assertRedirect(route('platform.tenants.index'));
        $this->assertSame('suspended', $individuallySuspended->fresh()->status);
    }

    private function platformAdmin(): User
    {
        $admin = User::factory()->create([
            'tenant_id' => null,
            'email' => 'support-admin-'.Str::lower(Str::random(8)).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        DB::table('platform_admins')->insert(['user_id' => $admin->id, 'is_active' => true, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);

        return $admin;
    }

    private function loginAsPlatform(User $admin): void
    {
        $this->post(route('platform.login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('platform.dashboard'));
        $this->completeMfa($admin);
    }

    /** @param array<string, mixed> $overrides */
    private function grantPayload(Tenant $tenant, User $admin, array $overrides = []): array
    {
        return array_merge([
            'target_tenant_id' => $tenant->id,
            'scope' => 'tenant_administration_read',
            'duration_minutes' => 60,
            'reason' => 'Investigate reported account configuration issue.',
            'support_ticket' => 'SUP-1001',
            'current_password' => 'password',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function grant(Tenant $tenant, User $admin, array $overrides = []): SupportAccessGrant
    {
        $now = now('UTC');

        return SupportAccessGrant::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'requested_by_user_id' => $admin->id,
            'scope' => 'tenant_administration_read',
            'reason' => 'Investigate reported account configuration issue.',
            'support_ticket' => 'SUP-1001',
            'granted_at' => $now,
            'expires_at' => $now->addMinutes(30),
        ], $overrides));
    }
}
