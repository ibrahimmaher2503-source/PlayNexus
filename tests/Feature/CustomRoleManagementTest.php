<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CustomRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CustomRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_custom_role_with_only_branch_view_and_audit(): void
    {
        [$tenant, $owner] = $this->owner();

        $response = $this->actingAs($owner)->postJson(route('roles.store'), [
            'name' => '  Branch viewer  ',
            'permissions' => ['branches.view'],
            'tenant_id' => Tenant::factory()->create()->id,
        ]);

        $response->assertCreated()->assertJsonPath('role.name', 'Branch viewer');
        $role = CustomRole::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertLessThanOrEqual(50, strlen($role->code));
        $this->assertSame(['branches.view'], $role->permissions()->pluck('permission')->all());
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_user_id' => $owner->id,
            'action' => 'custom_role.created',
            'subject_type' => 'custom_role',
            'subject_id' => (string) $role->id,
            'reason_code' => 'role_management',
        ]);
    }

    public function test_non_owner_and_foreign_roles_cannot_be_viewed_or_changed(): void
    {
        [$tenant, $owner] = $this->owner();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $foreignRole = CustomRole::create(['tenant_id' => Tenant::factory()->create()->id, 'name' => 'Foreign', 'code' => 'foreign']);

        $this->actingAs($staff)->get(route('roles.index'))->assertForbidden();
        $this->post(route('roles.store'), ['name' => 'Blocked'])->assertForbidden();
        $this->actingAs($owner)->patchJson(route('roles.update', $foreignRole), [
            'name' => 'Nope',
            'expected_lock_version' => 1,
        ])->assertNotFound();

        $this->assertDatabaseMissing('custom_roles', ['tenant_id' => $tenant->id, 'name' => 'Blocked']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_owner_update_is_atomic_and_rejects_a_stale_version_or_extra_permission(): void
    {
        [$tenant, $owner] = $this->owner();
        $role = CustomRole::create(['tenant_id' => $tenant->id, 'name' => 'Branch viewer', 'code' => 'branch_viewer']);
        $role->permissions()->create(['permission' => 'branches.view']);

        $this->actingAs($owner)->patchJson(route('roles.update', $role), [
            'name' => 'Updated viewer',
            'permissions' => ['branches.view'],
            'expected_lock_version' => 1,
        ])->assertOk()->assertJsonPath('role.lock_version', 2);

        $this->patchJson(route('roles.update', $role), [
            'name' => 'Stale viewer',
            'expected_lock_version' => 1,
        ])->assertStatus(409);
        $this->assertDatabaseHas('custom_roles', ['id' => $role->id, 'name' => 'Updated viewer', 'lock_version' => 2]);

        $this->patchJson(route('roles.update', $role), [
            'name' => 'Invalid viewer',
            'permissions' => ['branches.view', 'staff.manage'],
            'expected_lock_version' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions');
        $this->assertDatabaseMissing('custom_roles', ['name' => 'Invalid viewer']);
        $this->patchJson(route('roles.update', $role), [
            'name' => 'Updated viewer',
            'permissions' => [],
            'expected_lock_version' => 2,
        ])->assertOk()->assertJsonPath('role.lock_version', 3);
        $this->assertFalse($role->permissions()->exists());
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'custom_role.updated')->count());
    }

    public function test_custom_role_permission_grants_branch_view_without_changing_fixed_roles(): void
    {
        [$tenant, $owner] = $this->owner();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $role = CustomRole::create(['tenant_id' => $tenant->id, 'name' => 'Branch viewer', 'code' => 'branch_viewer']);
        $role->permissions()->create(['permission' => 'branches.view']);
        $user->branches()->attach($branch, ['tenant_id' => $tenant->id, 'role' => $role->code, 'is_active' => true]);

        $this->assertTrue(Gate::forUser($user)->allows('view', $branch));
        $role->permissions()->delete();
        $this->assertFalse(Gate::forUser($user)->allows('view', $branch));

        $fixed = User::factory()->create(['tenant_id' => $tenant->id]);
        $fixed->branches()->attach($branch, ['tenant_id' => $tenant->id, 'role' => 'cashier', 'is_active' => true]);
        $this->assertTrue(Gate::forUser($fixed)->allows('view', $branch));
    }

    public function test_role_page_explains_permission_groups_and_their_limits(): void
    {
        [$tenant, $owner] = $this->owner();
        $role = CustomRole::create(['tenant_id' => $tenant->id, 'name' => 'Branch viewer', 'code' => 'branch_viewer']);
        $role->permissions()->create(['permission' => 'branches.view']);

        $this->actingAs($owner)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee(__('roles.permission_groups_heading'))
            ->assertSee(__('roles.permission_group_branch_access'))
            ->assertSee(__('roles.permission_group_branch_access_description'))
            ->assertSee(__('roles.permission_impact_note'))
            ->assertSee('fieldset', false);
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $owner];
    }
}
