<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Child;
use App\Models\CustomRole;
use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class FamilyAuthorizationRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_read_masked_family_but_cannot_mutate_guardian_or_children(): void
    {
        [$tenant, $owner, $guardian, $child] = $this->family();
        $cashier = $this->staff($tenant, 'cashier');

        $this->actingAs($cashier)
            ->getJson(route('families.show', $guardian))
            ->assertOk()
            ->assertJsonPath('guardian.phone_e164', $guardian->maskedPhone())
            ->assertJsonPath('guardian.email', 'p••••@example.test')
            ->assertJsonPath('guardian.preferred_locale', null)
            ->assertJsonPath('children.0.date_of_birth', null)
            ->assertJsonPath('children.0.age', $child->date_of_birth->age)
            ->assertJsonMissing(['phone_e164' => $guardian->phone_e164])
            ->assertJsonMissing(['email' => $guardian->email])
            ->assertJsonMissing(['date_of_birth' => $child->date_of_birth->format('Y-m-d')]);

        $this->actingAs($cashier)
            ->patchJson(route('families.update', $guardian), $this->guardianPayload())
            ->assertForbidden();
        $this->actingAs($cashier)
            ->patchJson(route('families.children.update', [$guardian, $child]), $this->childPayload())
            ->assertForbidden();
        $this->actingAs($cashier)
            ->postJson(route('families.children.store', $guardian), $this->newChildPayload())
            ->assertForbidden();

        $this->assertFalse(Gate::forUser($cashier)->allows('manageRelationships', $guardian));
        $this->assertDatabaseHas('guardians', ['id' => $guardian->id, 'email' => 'parent@example.test', 'preferred_locale' => 'ar']);
        $this->assertDatabaseHas('children', ['id' => $child->id, 'full_name' => 'Existing child']);
        $this->assertDatabaseCount('children', 1);
        $this->assertSame(0, DB::table('audit_logs')->where('action', '!=', 'security.request_denied')->count());

        // The owner fixture remains authorized and proves the denial is role-based.
        $this->assertTrue(Gate::forUser($owner)->allows('update', $guardian));
    }

    public function test_cashier_html_does_not_render_management_forms(): void
    {
        [$tenant, , $guardian, $child] = $this->family();
        $cashier = $this->staff($tenant, 'cashier');

        $this->actingAs($cashier)
            ->get(route('families.show', $guardian))
            ->assertOk()
            ->assertSee($guardian->maskedPhone())
            ->assertDontSee(route('families.update', $guardian), false)
            ->assertDontSee(route('families.children.update', [$guardian, $child]), false)
            ->assertDontSee(route('families.children.store', $guardian), false);
    }

    public function test_owner_manager_and_reception_keep_the_approved_management_abilities(): void
    {
        [$tenant, $owner, $guardian] = $this->family();

        foreach ([$owner, $this->staff($tenant, 'branch_manager'), $this->staff($tenant, 'reception_staff')] as $actor) {
            $this->assertTrue(Gate::forUser($actor)->allows('update', $guardian));
            $this->assertTrue(Gate::forUser($actor)->allows('createChild', $guardian));
            $this->assertTrue(Gate::forUser($actor)->allows('updateChild', $guardian));
            $this->assertTrue(Gate::forUser($actor)->allows('manageRelationships', $guardian));
            $this->assertTrue(Gate::forUser($actor)->allows('viewSensitiveData', $guardian));
        }
    }

    public function test_custom_branch_view_role_cannot_read_or_mutate_family_records(): void
    {
        [$tenant, , $guardian, $child] = $this->family();
        $role = CustomRole::create(['tenant_id' => $tenant->id, 'name' => 'Viewer', 'code' => 'viewer']);
        $role->permissions()->create(['permission' => 'branches.view']);
        $viewer = $this->staff($tenant, $role->code);

        $this->actingAs($viewer)->getJson(route('families.show', $guardian))->assertForbidden();
        $this->actingAs($viewer)->patchJson(route('families.update', $guardian), $this->guardianPayload())->assertForbidden();
        $this->actingAs($viewer)->patchJson(route('families.children.update', [$guardian, $child]), $this->childPayload())->assertForbidden();
        $this->actingAs($viewer)->postJson(route('families.children.store', $guardian), $this->newChildPayload())->assertForbidden();
    }

    /** @return array{Tenant, User, Guardian, Child} */
    private function family(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Parent One',
            'phone_e164' => '+201001112233',
            'email' => 'parent@example.test',
            'preferred_locale' => 'ar',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $child = Child::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Existing child',
            'date_of_birth' => '2018-01-02',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        DB::table('guardian_child')->insert([
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'relationship_type' => 'parent',
            'is_active' => true,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $owner, $guardian, $child];
    }

    private function staff(Tenant $tenant, string $role): User
    {
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $staff->branches()->attach($branch, ['tenant_id' => $tenant->id, 'role' => $role, 'is_active' => true]);

        return $staff;
    }

    /** @return array<string, mixed> */
    private function guardianPayload(): array
    {
        return ['guardian_name' => 'Changed', 'phone' => '01001112233', 'email' => 'changed@example.test', 'preferred_locale' => 'en', 'expected_version' => 1];
    }

    /** @return array<string, mixed> */
    private function childPayload(): array
    {
        return ['child_name' => 'Changed child', 'date_of_birth' => '2019-01-02', 'expected_version' => 1];
    }

    /** @return array<string, mixed> */
    private function newChildPayload(): array
    {
        return ['child_name' => 'Forbidden child', 'date_of_birth' => null, 'relationship_type' => 'parent', 'expected_version' => 1];
    }
}
