<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantBranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_comes_from_authenticated_user_and_ignores_client_tenant_id(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($user);

        $this->assertSame($tenant->id, app(TenantContext::class)->current()->id);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $user->branches()->attach($branch, ['role' => 'reception', 'is_active' => true]);
        $this->getJson('/branches/'.$branch->id.'?tenant_id='.$other->id)->assertOk();
        $this->assertSame($tenant->id, app(TenantContext::class)->current()->id);
    }

    public function test_branch_access_is_assignment_scoped_and_deny_by_default(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $assigned = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $unassigned = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $foreign = Branch::factory()->create(['tenant_id' => $other->id]);
        $user->branches()->attach($assigned, ['role' => 'reception', 'is_active' => true]);
        $this->actingAs($user);

        $this->getJson('/branches/'.$assigned->id)->assertOk();
        $this->getJson('/branches/'.$unassigned->id)->assertNotFound();
        $this->getJson('/branches/'.$foreign->id)->assertNotFound();
    }

    public function test_inactive_assignment_branch_and_tenant_are_denied(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $user->branches()->attach($branch, ['role' => 'cashier', 'is_active' => false]);
        $this->actingAs($user);

        $this->getJson('/branches/'.$branch->id)->assertNotFound();
        $user->branches()->updateExistingPivot($branch, ['is_active' => true]);
        $branch->update(['is_active' => false]);
        $this->getJson('/branches/'.$branch->id)->assertNotFound();
        $branch->update(['is_active' => true]);
        $tenant->update(['is_active' => false]);
        $this->getJson('/branches/'.$branch->id)->assertNotFound();
    }

    public function test_schema_has_scope_constraints_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('branches', 'tenant_id'));
        $this->assertTrue(Schema::hasTable('branch_user'));
        $this->assertTrue(collect(Schema::getIndexes('branches'))->contains(fn ($index) => in_array('tenant_id', $index['columns'], true)));
        $this->assertTrue(collect(Schema::getIndexes('branch_user'))->contains(fn ($index) => in_array('branch_id', $index['columns'], true) && in_array('user_id', $index['columns'], true)));
    }
}
