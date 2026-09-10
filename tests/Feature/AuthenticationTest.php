<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_can_log_in(): void
    {
        [, $user] = $this->staff();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        [, $user] = $this->staff();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
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
        $this->post(route('branch-context.store', $branch))->assertNotFound();
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
            ->assertNotFound()
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
}
