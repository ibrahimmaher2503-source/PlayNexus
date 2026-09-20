<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class OwnerBranchAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_select_and_read_all_active_own_tenant_branches_without_assignments(): void
    {
        [$tenant, $owner] = $this->owner();
        $first = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Owner First']);
        $second = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Owner Second']);
        $inactive = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Inactive Hidden', 'is_active' => false]);
        $foreign = Branch::factory()->create(['name' => 'Foreign Hidden']);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee([$first->name, $second->name, $inactive->name])
            ->assertDontSee($foreign->name);

        $this->post(route('branch-context.store', $second))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('branch_id', $second->id);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee($second->name)
            ->assertSee(__('actor_dashboard.titles.owner'));

        $this->getJson('/branches/'.$first->id)
            ->assertOk()
            ->assertJsonPath('id', $first->id);
        $this->getJson('/branches/'.$second->id)
            ->assertOk()
            ->assertJsonPath('id', $second->id);
    }

    public function test_owner_cannot_select_or_read_inactive_or_foreign_branches(): void
    {
        [$tenant, $owner] = $this->owner();
        $inactive = Branch::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);
        $foreign = Branch::factory()->create();

        $this->actingAs($owner);

        foreach ([$inactive, $foreign] as $branch) {
            $this->post(route('branch-context.store', $branch))->assertNotFound();
            $this->getJson('/branches/'.$branch->id)->assertNotFound();
        }
    }

    public function test_branch_policy_reads_branch_state_fresh(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $branch->refresh();

        DB::table('branches')->where('id', $branch->id)->update(['is_active' => false]);

        $this->assertTrue($branch->is_active);
        $this->assertFalse(Gate::forUser($owner)->allows('view', $branch));
    }

    public function test_revoked_owner_loses_branch_access_on_next_request(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)->withSession(['branch_id' => $branch->id]);
        DB::table('tenant_owners')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $owner->id)
            ->delete();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSessionMissing('branch_id')
            ->assertDontSee($branch->name);
        $this->getJson('/branches/'.$branch->id)->assertNotFound();
    }

    public function test_revoked_owner_keeps_context_when_staff_assignment_still_allows_branch(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $owner->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->actingAs($owner)->withSession(['branch_id' => $branch->id]);
        DB::table('tenant_owners')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $owner->id)
            ->delete();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas('branch_id', $branch->id)
            ->assertSee($branch->name);
        $this->getJson('/branches/'.$branch->id)->assertOk();
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
