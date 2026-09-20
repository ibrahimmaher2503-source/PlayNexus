<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_exposes_an_accessible_password_visibility_control(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-pn-password', false)
            ->assertSee('data-pn-password-toggle', false)
            ->assertSee('aria-controls="password"', false)
            ->assertSee(__('Show password'));
    }

    public function test_active_staff_can_log_in(): void
    {
        [, $user] = $this->staff();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $user->tenant_id,
            'actor_user_id' => $user->id,
            'action' => 'auth.login_succeeded',
            'outcome' => 'success',
            'reason_code' => 'credentials_accepted',
        ]);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        [, $user] = $this->staff();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $user->tenant_id,
            'actor_user_id' => $user->id,
            'action' => 'auth.login_failed',
            'outcome' => 'failure',
        ]);
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        [, $user] = $this->staff();

        foreach (range(1, 5) as $attempt) {
            $this->from(route('login'))
                ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertRedirect(route('login'));
        }

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertTooManyRequests();
    }

    public function test_successful_login_regenerates_the_session(): void
    {
        [, $user] = $this->staff();
        $this->get(route('login'));
        $sessionId = $this->app['session']->getId();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertNotSame($sessionId, $this->app['session']->getId());
    }

    public function test_logout_invalidates_the_session_and_clears_branch_context(): void
    {
        [$tenant, $user] = $this->staff();
        $branch = $this->branchFor($tenant, $user);
        $this->actingAs($user)->withSession(['branch_id' => $branch->id]);
        $sessionId = $this->app['session']->getId();

        $this->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('branch_id');

        $this->assertGuest();
        $this->assertNotSame($sessionId, $this->app['session']->getId());
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_user_id' => $user->id,
            'action' => 'auth.logout',
            'outcome' => 'success',
        ]);
    }

    public function test_suspended_tenant_can_log_out(): void
    {
        [$tenant, $user] = $this->staff();
        $branch = $this->branchFor($tenant, $user);
        $tenant->update(['is_active' => false]);
        $this->actingAs($user)->withSession(['branch_id' => $branch->id]);
        $sessionId = $this->app['session']->getId();

        $this->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('branch_id');

        $this->assertGuest();
        $this->assertNotSame($sessionId, $this->app['session']->getId());
    }

    public function test_array_email_is_rejected_by_login_validation(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), ['email' => ['not-an-email'], 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_unauthenticated_application_access_redirects_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_sensitive_denial_is_audited_without_route_identifiers(): void
    {
        [$tenant, $user] = $this->staff();
        $branch = $this->branchFor($tenant, $user);

        $this->actingAs($user)->withSession(['branch_id' => $branch->id])
            ->get(route('audit.export'))
            ->assertForbidden();

        $audit = DB::table('audit_logs')->where('action', 'security.request_denied')->sole();
        $this->assertSame($tenant->id, $audit->tenant_id);
        $this->assertSame($user->id, $audit->actor_user_id);
        $this->assertSame('forbidden', $audit->reason_code);
        $this->assertSame(26, strlen($audit->subject_id));
        $this->assertStringNotContainsString('audit.export', json_encode($audit));
    }

    public function test_active_assigned_branch_can_be_selected(): void
    {
        [$tenant, $user] = $this->staff();
        $branch = $this->branchFor($tenant, $user);

        $this->actingAs($user)
            ->post(route('branch-context.store', $branch))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('branch_id', $branch->id);

        $this->get(route('dashboard'))->assertOk()->assertSee([$user->name, $tenant->name, $branch->name]);
    }

    public function test_unassigned_branch_cannot_be_selected(): void
    {
        [$tenant, $user] = $this->staff();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->post(route('branch-context.store', $branch))
            ->assertNotFound()
            ->assertSessionMissing('branch_id');
    }

    public function test_cross_tenant_branch_cannot_be_selected(): void
    {
        [, $user] = $this->staff();
        $branch = Branch::factory()->create();

        $this->actingAs($user)
            ->post(route('branch-context.store', $branch))
            ->assertNotFound()
            ->assertSessionMissing('branch_id');
    }

    public function test_inactive_branch_assignment_and_tenant_cannot_be_selected(): void
    {
        [$tenant, $user] = $this->staff();
        $branch = $this->branchFor($tenant, $user);

        $branch->update(['is_active' => false]);
        $this->actingAs($user)->post(route('branch-context.store', $branch))->assertNotFound();

        $branch->update(['is_active' => true]);
        $user->branches()->updateExistingPivot($branch, ['is_active' => false]);
        $this->post(route('branch-context.store', $branch))->assertNotFound();

        $user->branches()->updateExistingPivot($branch, ['is_active' => true]);
        $tenant->update(['is_active' => false]);
        $this->post(route('branch-context.store', $branch))->assertRedirect(route('login'));
    }

    public function test_invalid_selected_branch_is_removed_from_the_session(): void
    {
        [$tenant, $user] = $this->staff();
        $branch = $this->branchFor($tenant, $user);

        $user->branches()->updateExistingPivot($branch, ['is_active' => false]);
        $this->actingAs($user)
            ->withSession(['branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionMissing('branch_id');

        $user->branches()->updateExistingPivot($branch, ['is_active' => true]);
        $branch->update(['is_active' => false]);
        $this->withSession(['branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionMissing('branch_id');

        $branch->update(['is_active' => true]);
        $tenant->update(['is_active' => false]);
        $this->withSession(['branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('branch_id');
    }

    public function test_inactive_tenant_cannot_log_in(): void
    {
        [$tenant, $user] = $this->staff();
        $tenant->update(['is_active' => false]);

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_suspending_invalidates_old_sessions_and_reactivation_requires_fresh_login(): void
    {
        [$tenant, $user] = $this->staff();
        $branch = $this->branchFor($tenant, $user);
        $user->refresh();
        $oldAuthVersion = (int) $user->auth_version;
        [$admin] = $this->platformAdmin();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->loginAsPlatform($admin);
        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'suspended',
            'expected_status' => 'active',
            'reason_code' => 'access_review',
            'reason' => 'Security review requires immediate suspension.',
        ])->assertRedirect(route('platform.tenants.index'));

        $this->assertSame($oldAuthVersion + 1, (int) $user->fresh()->auth_version);
        $this->actingAs($user)
            ->withSession(['auth_version' => $oldAuthVersion, 'branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('branch_id');
        $this->assertGuest();

        $this->loginAsPlatform($admin);
        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'active',
            'expected_status' => 'suspended',
            'reason_code' => 'setup_change',
            'reason' => 'Security review completed and access may resume.',
        ])->assertRedirect(route('platform.tenants.index'));

        $this->actingAs($user)
            ->withSession(['auth_version' => $oldAuthVersion, 'branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_pending_transition_invalidates_old_sessions_and_reactivation_requires_fresh_login(): void
    {
        [$tenant, $user] = $this->staff();
        $user->refresh();
        $oldAuthVersion = (int) $user->auth_version;
        [$admin] = $this->platformAdmin();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->loginAsPlatform($admin);

        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'pending',
            'expected_status' => 'active',
            'reason_code' => 'setup_change',
            'reason' => 'Return account to pending setup.',
        ])->assertRedirect(route('platform.tenants.index'));

        $this->assertSame($oldAuthVersion + 1, (int) $user->fresh()->auth_version);
        $this->actingAs($user)
            ->withSession(['auth_version' => $oldAuthVersion])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $this->loginAsPlatform($admin);
        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'active',
            'expected_status' => 'pending',
            'reason_code' => 'setup_change',
            'reason' => 'Setup completed and account is active.',
        ])->assertRedirect(route('platform.tenants.index'));

        $this->assertSame($oldAuthVersion + 1, (int) $user->fresh()->auth_version);
        $this->actingAs($user)
            ->withSession(['auth_version' => $oldAuthVersion])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
    }

    /** @return array{Tenant, User} */
    private function staff(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('password'),
        ]);

        return [$tenant, $user];
    }

    private function branchFor(Tenant $tenant, User $user): Branch
    {
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $user->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => 'reception',
            'is_active' => true,
        ]);

        return $branch;
    }

    /** @return array{User} */
    private function platformAdmin(): array
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'email' => 'platform-'.Str::lower(Str::random(8)).'@example.test',
            'password' => Hash::make('password'),
        ]);
        DB::table('platform_admins')->insert([
            'user_id' => $user->id,
            'is_active' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return [$user];
    }

    private function loginAsPlatform(User $admin): void
    {
        $this->post(route('platform.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect();
        $this->assertAuthenticatedAs($admin);
        $this->completeMfa($admin);
    }
}
