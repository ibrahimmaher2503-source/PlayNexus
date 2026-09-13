<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NavigationUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_navigation_uses_an_accessible_native_dialog_in_both_locales(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);

        foreach (['en', 'ar'] as $locale) {
            $response = $this->actingAs($owner)->withSession(['locale' => $locale])->get(route('dashboard'));
            $response->assertOk()
                ->assertSee('<dialog', false)
                ->assertSee('id="pn-mobile-menu"', false)
                ->assertSee('data-pn-menu-open', false)
                ->assertSee('data-pn-menu-close', false)
                ->assertSee(__('navigation.close_menu'));
        }
    }

    public function test_selected_branch_opens_a_role_dashboard_with_scoped_quick_actions(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Front desk']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($owner)
            ->withSession(['branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.workspace_title'))
            ->assertSee(__('dashboard.quick_actions'))
            ->assertSee($branch->name)
            ->assertSee(route('families.index'), false)
            ->assertSee(route('tickets.index'), false)
            ->assertSee(route('sessions.index'), false)
            ->assertDontSee(__('dashboard.gate_title'));
    }
}
