<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_gets_a_tenant_scoped_business_dashboard(): void
    {
        [$tenant, $owner] = $this->owner();
        $first = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alpha Downtown']);
        Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alpha Mall']);
        $foreign = Branch::factory()->create(['name' => 'Foreign Secret Branch']);

        $this->actingAs($owner)
            ->withSession(['branch_id' => $first->id])
            ->get(route('dashboard', ['tenant_id' => $foreign->tenant_id, 'branch_id' => $foreign->id]))
            ->assertOk()
            ->assertSee('data-pn-actor="owner"', false)
            ->assertSee(__('actor_dashboard.titles.owner'))
            ->assertSee(['Alpha Downtown', 'Alpha Mall'])
            ->assertDontSee('Foreign Secret Branch')
            ->assertSee(route('branches.manage'), false)
            ->assertSee(route('staff.index'), false)
            ->assertSee(route('reports.index'), false)
            ->assertSee(route('tenant.settings.edit'), false);
    }

    public function test_manager_only_sees_assigned_branches_and_gets_operational_actions_after_selection(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        $first = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Manager One']);
        $second = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Manager Two']);
        $unassigned = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Unassigned Secret']);
        $this->assign($manager, $first, 'branch_manager');
        $this->assign($manager, $second, 'branch_manager');

        $this->actingAs($manager)
            ->withSession(['branch_id' => $unassigned->id])
            ->get(route('dashboard', ['branch_id' => $unassigned->id]))
            ->assertOk()
            ->assertSee('data-pn-actor="staff"', false)
            ->assertSee(['Manager One', 'Manager Two'])
            ->assertDontSee('Unassigned Secret');

        $this->post(route('branch-context.store', $first))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-pn-actor="branch_manager"', false)
            ->assertSee(route('sessions.index'), false)
            ->assertSee(route('pos.index'), false)
            ->assertSee(route('reports.index', ['branch_id' => $first->id]), false)
            ->assertDontSee(__('actor_dashboard.actions.staff.label'));

        $this->post(route('branch-context.store', $unassigned))->assertNotFound();
    }

    public function test_reception_dashboard_prioritizes_front_desk_and_hides_finance_and_administration(): void
    {
        $tenant = Tenant::factory()->create();
        $reception = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $this->assign($reception, $branch, 'reception');

        $this->actingAs($reception)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-pn-actor="reception"', false)
            ->assertSee(__('actor_dashboard.titles.reception'))
            ->assertSee(route('families.index'), false)
            ->assertSee(route('tickets.index'), false)
            ->assertSee(route('sessions.index'), false)
            ->assertDontSee(__('actor_dashboard.actions.pos.label'))
            ->assertDontSee(__('actor_dashboard.actions.staff.label'))
            ->assertDontSee(__('actor_dashboard.cash_sales'));
    }

    public function test_cashier_dashboard_exposes_settlement_actions_without_front_desk_or_admin_actions(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $this->assign($cashier, $branch, 'cashier');

        $this->actingAs($cashier)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-pn-actor="cashier"', false)
            ->assertSee(__('actor_dashboard.titles.cashier'))
            ->assertSee(route('pos.index'), false)
            ->assertSee(route('transactions.index'), false)
            ->assertSee(route('sessions.index', ['status' => 'pending_payment']), false)
            ->assertDontSee(__('actor_dashboard.actions.families.label'))
            ->assertDontSee(__('actor_dashboard.actions.staff.label'))
            ->assertSee(__('actor_dashboard.cash_sales'));
    }

    public function test_removed_assignment_invalidates_stale_dashboard_branch_context(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Revoked Branch']);
        $this->assign($staff, $branch, 'reception');

        $this->actingAs($staff)->withSession(['branch_id' => $branch->id]);
        DB::table('branch_user')->where('user_id', $staff->id)->where('branch_id', $branch->id)->update(['is_active' => false]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSessionMissing('branch_id')
            ->assertSee('data-pn-actor="staff"', false)
            ->assertDontSee('Revoked Branch');
    }

    public function test_dashboard_is_bilingual_and_has_accessible_page_structure(): void
    {
        [$tenant, $owner] = $this->owner();
        Branch::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)->withSession(['locale' => 'ar'])->get(route('dashboard'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('actor_dashboard.titles.owner'))
            ->assertSee('aria-labelledby="quick-actions-heading"', false)
            ->assertSee('data-pn-primary-action', false);
    }

    public function test_owner_branch_overview_labels_each_kpi_with_its_branch_local_date(): void
    {
        Carbon::setTestNow('2026-09-17 22:30:00 UTC');
        [$tenant, $owner] = $this->owner();
        Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Cairo Branch', 'timezone' => 'Africa/Cairo']);
        Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'New York Branch', 'timezone' => 'America/New_York']);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Africa/Cairo · 2026-09-18')
            ->assertSee('America/New_York · 2026-09-17');
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$tenant, $owner];
    }

    private function assign(User $user, Branch $branch, string $role): void
    {
        DB::table('branch_user')->insert([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
