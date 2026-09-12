<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
        ])->assertRedirect(route('platform.tenants.index'));

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
        ]);

        $response->assertForbidden();
        $this->assertStringNotContainsString($foreign->internal_identifier, $response->getContent());
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
        $this->assertSame($counts['platform_audits'] + 1, DB::table('platform_audit_logs')->count());
        $this->assertSame($tenant->id, $owner->tenant_id);
        $this->assertSame('invited', $owner->status);
        $this->assertDatabaseHas('tenant_owners', ['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

        $audit = DB::table('platform_audit_logs')
            ->where('target_tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame('tenant', $audit->subject_type);
        $this->assertSame((string) $tenant->id, $audit->subject_id);
        $this->assertSame('success', $audit->outcome);
        $this->assertNotEmpty($audit->request_id);
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
        $this->assertSame($counts['platform_audits'] + 1, DB::table('platform_audit_logs')->count());
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
        Schema::drop('platform_audit_logs');

        $this->post(route('platform.tenants.store'), $payload)
            ->assertStatus(500);

        $this->assertSame($counts['tenants'], Tenant::query()->count());
        $this->assertSame($counts['users'], User::query()->count());
        $this->assertSame($counts['tenant_owners'], DB::table('tenant_owners')->count());
        $this->assertDatabaseMissing('tenants', ['internal_identifier' => strtoupper($payload['internal_identifier'])]);
        $this->assertDatabaseMissing('users', ['email' => $payload['initial_owner_email']]);
    }

    public function test_stale_tenant_status_returns_409_without_audit_or_version_change(): void
    {
        [$admin] = $this->platformAdmin();
        $tenant = $this->tenant(['status' => 'active', 'is_active' => true]);
        $version = (int) $tenant->lock_version;
        DB::table('tenants')->whereKey($tenant->id)->update([
            'status' => 'suspended',
            'is_active' => false,
        ]);

        $this->loginAsPlatform($admin);
        $counts = $this->platformCounts();
        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'active',
            'expected_status' => 'active',
            'reason_code' => 'correction',
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

        $this->actingAs($staff)
            ->withSession(['branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertOk();

        DB::table('tenants')->whereKey($tenant->id)->update([
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
        ])->assertRedirect(route('platform.tenants.index'));
        $this->assertAuthenticatedAs($admin);
    }

    private function tenant(array $overrides = []): Tenant
    {
        return Tenant::factory()->create(array_merge([
            'internal_identifier' => 'tenant-'.Str::lower(Str::random(8)),
        ], $overrides));
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
