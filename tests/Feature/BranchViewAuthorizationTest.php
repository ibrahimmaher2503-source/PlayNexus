<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BranchViewAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_branch_roles_and_legacy_reception_can_list_select_and_read(): void
    {
        foreach (['branch_manager', 'reception_staff', 'reception', 'cashier'] as $role) {
            [, $user, $branch] = $this->assignedBranch($role);
            $this->actingAs($user);

            $this->assertTrue(Gate::forUser($user)->allows('view', $branch));
            $this->get(route('dashboard'))->assertOk()->assertSee($branch->name);
            $this->post(route('branch-context.store', $branch))
                ->assertRedirect(route('dashboard'))
                ->assertSessionHas('branch_id', $branch->id);
            $this->getJson('/branches/'.$branch->id)->assertOk();
        }
    }

    public function test_unsupported_roles_are_hidden_and_denied_by_direct_http_calls(): void
    {
        foreach (['', 'unknown', 'game_operator', 'parent_guardian', 'tenant_owner', 'super_admin'] as $role) {
            [, $user, $branch] = $this->assignedBranch($role);
            $this->actingAs($user);

            $this->assertFalse(Gate::forUser($user)->allows('view', $branch));
            $this->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee($branch->name);
            $this->post(route('branch-context.store', $branch))->assertForbidden();
            $this->getJson('/branches/'.$branch->id)->assertForbidden();
        }
    }

    public function test_a_role_on_one_branch_does_not_authorize_another_branch(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $permitted = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Permitted']);
        $denied = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Denied']);
        $user->branches()->attach($permitted, [
            'tenant_id' => $tenant->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
        $user->branches()->attach($denied, [
            'tenant_id' => $tenant->id,
            'role' => 'game_operator',
            'is_active' => true,
        ]);
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Permitted')
            ->assertDontSee('Denied');
        $this->post(route('branch-context.store', $denied))->assertForbidden();
        $this->getJson('/branches/'.$denied->id)->assertForbidden();
    }

    public function test_revoked_role_clears_stored_branch_context_and_denies_access(): void
    {
        [$tenant, $user, $branch] = $this->assignedBranch('reception_staff');
        $this->actingAs($user)->withSession(['branch_id' => $branch->id]);
        $user->load('branches');
        $user->branches()->updateExistingPivot($branch, ['role' => 'game_operator']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSessionMissing('branch_id')
            ->assertDontSee($branch->name);
        $this->getJson('/branches/'.$branch->id)->assertForbidden();
        $this->assertFalse(Gate::forUser($user)->allows('view', $branch));
    }

    public function test_foreign_unassigned_and_inactive_scope_remains_not_found(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $assigned = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $unassigned = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $foreign = Branch::factory()->create(['tenant_id' => $other->id]);
        $inactive = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $user->branches()->attach($assigned, [
            'tenant_id' => $tenant->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
        $user->branches()->attach($inactive, [
            'tenant_id' => $tenant->id,
            'role' => 'cashier',
            'is_active' => false,
        ]);
        $inactive->update(['is_active' => false]);
        $this->actingAs($user);

        foreach ([$unassigned, $foreign, $inactive] as $branch) {
            $this->post(route('branch-context.store', $branch))->assertNotFound();
            $this->getJson('/branches/'.$branch->id)->assertNotFound();
        }

        $tenant->update(['is_active' => false]);
        $this->getJson('/branches/'.$assigned->id)->assertNotFound();
    }

    /** @return array{Tenant, User, Branch} */
    private function assignedBranch(string $role): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('password'),
        ]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $user->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return [$tenant, $user, $branch];
    }
}
