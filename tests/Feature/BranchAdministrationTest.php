<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BranchAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('branches.manage')) {
            Route::middleware(['auth', 'tenant.access'])->group(function (): void {
                require base_path('routes/branches-admin.php');
            });
        }
    }

    public function test_owner_can_list_all_own_tenant_branches_including_inactive(): void
    {
        [$tenant, $owner] = $this->owner();
        $active = $this->branch($tenant, 'Active branch');
        $inactive = $this->branch($tenant, 'Inactive branch', false);
        $foreign = $this->branch(Tenant::factory()->create(), 'Foreign branch');

        $this->actingAs($owner)
            ->get(route('branches.manage'))
            ->assertOk()
            ->assertViewIs('branches.manage')
            ->assertSee([$active->name, $inactive->name])
            ->assertDontSee($foreign->name)
            ->assertSee(__('branch_settings.page_title'))
            ->assertDontSee('branch_settings.title')
            ->assertSee(__('branches.create'));
    }

    public function test_non_owner_cannot_view_or_mutate_branch_administration(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = $this->branch($tenant, 'Protected branch');

        $this->actingAs($staff)
            ->get(route('branches.manage'))
            ->assertForbidden();

        $this->post(route('branches.store'), ['name' => 'Blocked branch'])->assertForbidden();
        $this->patch(route('branches.status', $branch), [
            'is_active' => false,
            'expected_is_active' => true,
            'reason_code' => 'correction',
        ])->assertForbidden();

        $this->assertDatabaseMissing('branches', ['name' => 'Blocked branch']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_owner_can_create_trimmed_active_branch_and_request_fields_cannot_change_scope(): void
    {
        [$tenant, $owner] = $this->owner();
        $foreign = Tenant::factory()->create();

        $this->actingAs($owner)
            ->post(route('branches.store'), [
                'name' => '  New reception  ',
                'tenant_id' => $foreign->id,
                'id' => 987654,
            ])
            ->assertRedirect(route('branches.manage'))
            ->assertSessionHas('success', __('branches.created'));

        $branch = Branch::query()->where('tenant_id', $tenant->id)->where('name', 'New reception')->sole();
        $this->assertTrue((bool) $branch->is_active);
        $this->assertDatabaseMissing('branches', ['tenant_id' => $foreign->id, 'name' => 'New reception']);

        $audit = DB::table('audit_logs')->where('action', 'branch.created')->sole();
        $this->assertSame($tenant->id, $audit->tenant_id);
        $this->assertSame($branch->id, $audit->branch_id);
        $this->assertSame($owner->id, $audit->actor_user_id);
        $this->assertSame('branch', $audit->subject_type);
        $this->assertSame((string) $branch->id, $audit->subject_id);
        $this->assertSame('setup_change', $audit->reason_code);
        $this->assertNull($audit->before_json);
        $this->assertSame(['name' => 'New reception'], json_decode($audit->after_json, true));
        $this->assertNotEmpty($audit->request_id);
    }

    public function test_branch_name_is_trimmed_before_length_validation(): void
    {
        [$tenant, $owner] = $this->owner();

        $this->actingAs($owner)
            ->from(route('branches.manage'))
            ->post(route('branches.store'), ['name' => '  '])
            ->assertRedirect(route('branches.manage'))
            ->assertSessionHasErrors(['name' => __('branches.validation.name_required')]);

        $this->assertDatabaseCount('branches', 0);

        $this->postJson(route('branches.store'), ['name' => str_repeat('x', 121)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_foreign_status_target_is_hidden_before_validation(): void
    {
        [$tenant, $owner] = $this->owner();
        $foreign = $this->branch(Tenant::factory()->create(), 'Foreign branch');

        $this->actingAs($owner)
            ->patch(route('branches.status', $foreign), [])
            ->assertNotFound();

        $this->assertDatabaseHas('branches', ['id' => $foreign->id, 'is_active' => true]);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertTrue((bool) $tenant->is_active);
    }

    public function test_owner_can_deactivate_and_reactivate_branch_with_audited_state_changes(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Lifecycle branch');

        $this->actingAs($owner)
            ->patch(route('branches.status', $branch), [
                'is_active' => false,
                'expected_is_active' => true,
                'reason_code' => 'access_review',
            ])
            ->assertRedirect(route('branches.manage'))
            ->assertSessionHas('success', __('branches.status_updated'));

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'tenant_id' => $tenant->id, 'is_active' => 0]);
        $firstAudit = DB::table('audit_logs')->where('action', 'branch.status.changed')->sole();
        $this->assertSame(['is_active' => true], json_decode($firstAudit->before_json, true));
        $this->assertSame(['is_active' => false], json_decode($firstAudit->after_json, true));
        $this->assertStringNotContainsString($branch->name, (string) $firstAudit->before_json.(string) $firstAudit->after_json);

        $this->patch(route('branches.status', $branch), [
            'is_active' => true,
            'expected_is_active' => false,
            'reason_code' => 'correction',
        ])->assertRedirect(route('branches.manage'));

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => 1]);
        $this->assertDatabaseCount('audit_logs', 2);
        $secondAudit = DB::table('audit_logs')->where('action', 'branch.status.changed')->orderByDesc('id')->first();
        $this->assertSame(['is_active' => false], json_decode($secondAudit->before_json, true));
        $this->assertSame(['is_active' => true], json_decode($secondAudit->after_json, true));
    }

    public function test_stale_status_returns_conflict_without_audit(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Stale branch');
        DB::table('branches')->where('id', $branch->id)->update(['is_active' => false]);

        $this->actingAs($owner)
            ->patch(route('branches.status', $branch), [
                'is_active' => false,
                'expected_is_active' => true,
                'reason_code' => 'correction',
            ])
            ->assertStatus(409)
            ->assertSee(__('branches.conflict'));

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => 0]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_noop_status_is_successful_without_audit(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Already active');

        $this->actingAs($owner)
            ->patch(route('branches.status', $branch), [
                'is_active' => true,
                'expected_is_active' => true,
                'reason_code' => 'correction',
            ])
            ->assertRedirect(route('branches.manage'))
            ->assertSessionHas('status_message', __('branches.no_change'));

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => 1]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_deactivating_selected_branch_clears_context_on_next_protected_request(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Selected branch');

        $this->actingAs($owner)->withSession(['branch_id' => $branch->id]);
        $this->patch(route('branches.status', $branch), [
            'is_active' => false,
            'expected_is_active' => true,
            'reason_code' => 'access_review',
        ])->assertRedirect(route('branches.manage'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSessionMissing('branch_id')
            ->assertDontSee($branch->name);
    }

    public function test_validation_rerender_keeps_each_row_on_persisted_status(): void
    {
        [$tenant, $owner] = $this->owner();
        $active = $this->branch($tenant, 'Active row');
        $inactive = $this->branch($tenant, 'Inactive row', false);
        $url = route('branches.manage');

        $this->actingAs($owner)
            ->from($url)
            ->patch(route('branches.status', $active), [
                'is_active' => 'invalid',
                'expected_is_active' => true,
                'reason_code' => 'correction',
            ])
            ->assertRedirect($url);

        $html = $this->get($url)->getContent();
        $activeForm = $this->formMarkup($html, $active->id);
        $inactiveForm = $this->formMarkup($html, $inactive->id);
        $this->assertStringContainsString('name="expected_is_active" value="1"', $activeForm);
        $this->assertStringContainsString('name="expected_is_active" value="0"', $inactiveForm);
        $this->assertStringContainsString('value="1" selected', $activeForm);
        $this->assertStringContainsString('value="0" selected', $inactiveForm);
        $this->assertStringNotContainsString('value="invalid"', $activeForm.$inactiveForm);
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

    private function branch(Tenant $tenant, string $name, bool $active = true): Branch
    {
        return Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'is_active' => $active,
        ]);
    }

    private function formMarkup(string $html, int $branchId): string
    {
        $start = strpos($html, 'id="branch-status-'.$branchId.'"');
        $this->assertNotFalse($start);
        $end = strpos($html, '</form>', $start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }
}
