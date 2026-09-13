<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TenantOwnerReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_read_own_tenant_without_branch_access(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner);

        $this->get(route('tenant.show'))
            ->assertOk()
            ->assertViewIs('tenant.show')
            ->assertViewHas('tenant', fn (Tenant $viewTenant): bool => $viewTenant->is($tenant));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('tenant.settings.edit'), false)
            ->assertSee(route('staff.index'), false)
            ->assertSee(route('branches.manage'), false)
            ->assertSee(route('audit.index'), false)
            ->assertDontSee(route('assignments.index'), false)
            ->assertDontSee(route('roles.index'), false);

        $this->getJson('/branches/'.$branch->id)->assertOk()->assertJsonPath('id', $branch->id);
    }

    public function test_staff_and_branch_owner_spoof_cannot_read_tenant(): void
    {
        foreach (['cashier', 'tenant_owner', 'super_admin'] as $role) {
            [$tenant, $user] = $this->staffWithRole($role);

            $this->actingAs($user);

            $this->assertFalse(Gate::forUser($user)->allows('view', $tenant));
            $this->get(route('tenant.show'))->assertForbidden();
            $this->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee(route('tenant.show'), false);
        }
    }

    public function test_duplicate_owner_assignments_are_rejected_by_composite_primary_key(): void
    {
        [$tenant, $owner] = $this->owner();

        $this->expectException(QueryException::class);
        DB::table('tenant_owners')->insert($this->ownerRow($tenant, $owner));
    }

    public function test_cross_tenant_owner_assignment_is_rejected_by_composite_foreign_key(): void
    {
        $tenant = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();
        $foreignUser = User::factory()->create(['tenant_id' => $foreign->id]);

        $this->expectException(QueryException::class);
        DB::table('tenant_owners')->insert($this->ownerRow($tenant, $foreignUser));
    }

    public function test_null_owner_user_is_rejected_by_schema(): void
    {
        $tenant = Tenant::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('tenant_owners')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_null_owner_tenant_is_rejected_by_schema(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(QueryException::class);
        DB::table('tenant_owners')->insert([
            'tenant_id' => null,
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_owner_assignment_revocation_is_read_fresh(): void
    {
        [$tenant, $owner] = $this->owner();
        $this->actingAs($owner);

        $this->get(route('tenant.show'))->assertOk();
        DB::table('tenant_owners')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $owner->id)
            ->delete();

        $this->assertFalse(Gate::forUser($owner)->allows('view', $tenant));
        $this->get(route('tenant.show'))->assertForbidden();
    }

    public function test_persisted_owner_status_is_read_fresh_and_revokes_http_access(): void
    {
        [$tenant, $owner] = $this->owner();
        $this->actingAs($owner);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $tenant));
        User::query()->whereKey($owner->id)->update(['status' => 'disabled']);

        $this->assertFalse(Gate::forUser($owner)->allows('view', $tenant));
        $this->get(route('tenant.show'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('branch_id');
        $this->assertGuest();
    }

    public function test_inactive_tenant_is_hidden_even_when_the_passed_model_was_loaded_active(): void
    {
        [$tenant, $owner] = $this->owner();
        $this->actingAs($owner);
        $tenant->refresh();

        Tenant::query()->whereKey($tenant->id)->update(['is_active' => false]);
        $this->assertTrue($tenant->is_active);

        $decision = Gate::forUser($owner)->inspect('view', $tenant);

        $this->assertFalse($decision->allowed());
        $this->assertSame(404, $decision->status());
        $this->get(route('tenant.show'))->assertNotFound();
    }

    public function test_foreign_tenant_policy_denies_as_not_found(): void
    {
        [$tenant, $owner] = $this->owner();
        $foreign = Tenant::factory()->create();

        $decision = Gate::forUser($owner)->inspect('view', $foreign);

        $this->assertFalse($decision->allowed());
        $this->assertSame(404, $decision->status());
        $this->assertTrue(Gate::forUser($owner)->allows('view', $tenant));
    }

    public function test_user_without_a_tenant_cannot_read_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => null]);

        $this->assertFalse(Gate::forUser($user)->allows('view', $tenant));
    }

    public function test_request_tenant_ids_cannot_switch_the_authenticated_scope(): void
    {
        [$tenant, $owner] = $this->owner();
        $foreign = Tenant::factory()->create(['name' => 'Foreign Tenant Must Stay Hidden']);
        $staff = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Own Tenant Staff',
        ]);
        User::factory()->create([
            'tenant_id' => $foreign->id,
            'name' => 'Foreign Tenant Staff',
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.show', ['tenant_id' => $foreign->id, 'id' => $foreign->id]))
            ->assertOk()
            ->assertViewHas('tenant', fn (Tenant $viewTenant): bool => $viewTenant->is($tenant))
            ->assertSee($tenant->name)
            ->assertSee($staff->name)
            ->assertDontSee($foreign->name)
            ->assertDontSee('Foreign Tenant Staff');
    }

    public function test_staff_list_is_tenant_scoped_selected_and_paginated(): void
    {
        [$tenant, $owner] = $this->owner();
        User::factory()->count(30)->create(['tenant_id' => $tenant->id]);
        $foreign = Tenant::factory()->create();
        $foreignUser = User::factory()->create(['tenant_id' => $foreign->id]);
        $expectedIds = User::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->actingAs($owner)
            ->get(route('tenant.show', ['page' => 2, 'tenant_id' => $foreign->id]))
            ->assertOk()
            ->assertViewHas('staff', function (LengthAwarePaginator $staff) use ($expectedIds, $foreignUser): bool {
                $this->assertSame(count($expectedIds), $staff->total());
                $this->assertSame(25, $staff->perPage());
                $this->assertSame(2, $staff->currentPage());
                $this->assertSame(array_slice($expectedIds, 25), $staff->getCollection()->modelKeys());
                $this->assertNotContains($foreignUser->id, $staff->getCollection()->modelKeys());

                $fields = array_keys($staff->first()->getAttributes());
                sort($fields);
                $this->assertSame(['email', 'id', 'name', 'status'], $fields);

                return true;
            });
    }

    public function test_arabic_empty_page_keeps_previous_pagination_navigation(): void
    {
        [$tenant, $owner] = $this->owner();
        User::factory()->count(30)->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->withSession(['locale' => 'ar'])
            ->get(route('tenant.show', ['page' => 2]))
            ->assertOk()
            ->assertSee(trans('tenant.previous_page', [], 'ar'), false);

        $this->actingAs($owner)
            ->withSession(['locale' => 'ar'])
            ->get(route('tenant.show', ['page' => 3]))
            ->assertOk()
            ->assertSee(trans('tenant.previous_page', [], 'ar'), false)
            ->assertSee('page=2', false);
    }

    public function test_owner_page_renders_in_english_and_arabic(): void
    {
        [$tenant, $owner] = $this->owner();
        $staff = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Visible Staff Member',
        ]);

        foreach ([['en', 'ltr'], ['ar', 'rtl']] as [$locale, $direction]) {
            $this->actingAs($owner)
                ->withSession(['locale' => $locale])
                ->get(route('tenant.show'))
                ->assertOk()
                ->assertSee('lang="'.$locale.'"', false)
                ->assertSee('dir="'.$direction.'"', false)
                ->assertSee($tenant->name)
                ->assertSee($staff->name);
        }
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert($this->ownerRow($tenant, $owner));

        return [$tenant, $owner];
    }

    /** @return array{Tenant, User} */
    private function staffWithRole(string $role): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $user->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return [$tenant, $user];
    }

    /** @return array<string, mixed> */
    private function ownerRow(Tenant $tenant, User $user): array
    {
        return [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
