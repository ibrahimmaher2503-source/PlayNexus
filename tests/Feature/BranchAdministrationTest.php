<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Subscription;
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
            ->assertSee(__('branches.deactivate'))
            ->assertSee(__('branches.deactivate_help'))
            ->assertSee(__('branches.reactivate'))
            ->assertSee(__('branches.reactivate_help'))
            ->assertDontSee('name="reason_code"', false)
            ->assertSee(__('branches.create'));
    }

    public function test_unassigned_staff_cannot_view_or_mutate_branch_administration(): void
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
        ])->assertNotFound();

        $this->assertDatabaseMissing('branches', ['name' => 'Blocked branch']);
        $this->assertSame(0, DB::table('audit_logs')->where('action', '!=', 'security.request_denied')->count());
    }

    public function test_branch_manager_sees_and_controls_only_assigned_branches_but_cannot_create(): void
    {
        [$tenant] = $this->owner();
        $manager = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $managed = $this->branch($tenant, 'Managed branch');
        $inactiveManaged = $this->branch($tenant, 'Inactive managed branch', false);
        $unassigned = $this->branch($tenant, 'Unassigned branch');
        $this->makeActivationReady($inactiveManaged);

        foreach ([$managed, $inactiveManaged] as $branch) {
            DB::table('branch_user')->insert([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'user_id' => $manager->id,
                'role' => 'branch_manager',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($manager)
            ->get(route('branches.manage'))
            ->assertOk()
            ->assertSee([$managed->name, $inactiveManaged->name])
            ->assertDontSee($unassigned->name)
            ->assertDontSee('action="'.route('branches.store').'"', false)
            ->assertSee(route('branches.manage'), false);

        $this->post(route('branches.store'), ['name' => 'Manager cannot create'])->assertForbidden();
        $this->patch(route('branches.status', $managed), [
            'is_active' => false,
            'expected_is_active' => true,
        ])->assertRedirect(route('branches.manage'));
        $this->patch(route('branches.status', $inactiveManaged), [
            'is_active' => true,
            'expected_is_active' => false,
        ])->assertRedirect(route('branches.manage'));
        $this->patch(route('branches.status', $unassigned), [
            'is_active' => false,
            'expected_is_active' => true,
        ])->assertNotFound();

        $this->assertDatabaseHas('branches', ['id' => $managed->id, 'is_active' => 0]);
        $this->assertDatabaseHas('branches', ['id' => $inactiveManaged->id, 'is_active' => 1]);
        $this->assertDatabaseHas('branches', ['id' => $unassigned->id, 'is_active' => 1]);
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'branch.status.changed')->count());
    }

    public function test_owner_can_create_trimmed_draft_branch_and_request_fields_cannot_change_scope(): void
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
        $this->assertFalse((bool) $branch->is_active);
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

    public function test_branch_creation_retry_returns_the_original_branch_without_duplicate_audit(): void
    {
        [$tenant, $owner] = $this->owner();
        $key = '4c6bb485-4701-44ed-82c9-26d15af0196f';

        $first = $this->actingAs($owner)->postJson(route('branches.store'), ['name' => 'Retry safe', 'creation_key' => $key]);
        $first->assertCreated()->assertJsonPath('created', true);
        $second = $this->postJson(route('branches.store'), ['name' => 'Retry safe', 'creation_key' => $key]);
        $second->assertOk()->assertJsonPath('created', false)->assertJsonPath('branch_id', $first->json('branch_id'));

        $this->postJson(route('branches.store'), ['name' => 'Changed payload', 'creation_key' => $key])->assertConflict();
        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('branches', ['tenant_id' => $tenant->id, 'creation_key' => $key, 'name' => 'Retry safe']);
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
        $this->makeActivationReady($branch);

        $this->actingAs($owner)
            ->patch(route('branches.status', $branch), [
                'is_active' => false,
                'expected_is_active' => true,
                'reason_code' => 'client-value-is-ignored',
            ])
            ->assertRedirect(route('branches.manage'))
            ->assertSessionHas('success', __('branches.status_updated'));

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'tenant_id' => $tenant->id, 'is_active' => 0]);
        $firstAudit = DB::table('audit_logs')->where('action', 'branch.status.changed')->sole();
        $this->assertSame(['is_active' => true], json_decode($firstAudit->before_json, true));
        $this->assertSame(['is_active' => false], json_decode($firstAudit->after_json, true));
        $this->assertSame('access_review', $firstAudit->reason_code);
        $this->assertStringNotContainsString($branch->name, (string) $firstAudit->before_json.(string) $firstAudit->after_json);

        $this->patch(route('branches.status', $branch), [
            'is_active' => true,
            'expected_is_active' => false,
        ])->assertRedirect(route('branches.manage'));

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => 1]);
        $this->assertDatabaseCount('audit_logs', 2);
        $secondAudit = DB::table('audit_logs')->where('action', 'branch.status.changed')->orderByDesc('id')->first();
        $this->assertSame(['is_active' => false], json_decode($secondAudit->before_json, true));
        $this->assertSame(['is_active' => true], json_decode($secondAudit->after_json, true));
    }

    public function test_draft_branch_cannot_activate_until_operational_configuration_is_complete(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Draft branch', false);
        $branch->forceFill(['code' => null])->save();

        $this->actingAs($owner)->patchJson(route('branches.status', $branch), [
            'is_active' => true,
            'expected_is_active' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_active');

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => 0]);
        $this->assertDatabaseCount('audit_logs', 0);

        $branch->forceFill([
            'code' => 'READY', 'capacity' => 20, 'timezone' => 'Africa/Cairo',
            'currency' => 'EGP', 'tax_rate_bps' => 1400, 'tax_mode' => 'exclusive',
            'receipt_prefix' => 'READY', 'payment_methods' => ['cash'],
        ])->save();
        foreach (range(1, 7) as $weekday) {
            DB::table('branch_opening_hours')->insert([
                'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'weekday' => $weekday,
                'opens_at' => $weekday === 1 ? '09:00' : null,
                'closes_at' => $weekday === 1 ? '18:00' : null,
                'is_closed' => $weekday !== 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->patchJson(route('branches.status', $branch), [
            'is_active' => true,
            'expected_is_active' => false,
        ])->assertOk()->assertJsonPath('changed', true);

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'branch.status.changed', 'branch_id' => $branch->id]);
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
        ])->assertRedirect(route('branches.manage'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSessionMissing('branch_id')
            ->assertSee(__('navigation.choose_branch'))
            ->assertSee($branch->name);
    }

    public function test_validation_rerender_keeps_each_row_action_based_on_persisted_status(): void
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
            ])
            ->assertRedirect($url);

        $html = $this->get($url)->getContent();
        $activeForm = $this->formMarkup($html, $active->id);
        $inactiveForm = $this->formMarkup($html, $inactive->id);
        $this->assertStringContainsString('name="expected_is_active" value="1"', $activeForm);
        $this->assertStringContainsString('name="expected_is_active" value="0"', $inactiveForm);
        $this->assertStringContainsString('name="is_active" value="0"', $activeForm);
        $this->assertStringContainsString(__('branches.deactivate'), $activeForm);
        $this->assertStringContainsString('name="is_active" value="1"', $inactiveForm);
        $this->assertStringContainsString(__('branches.reactivate'), $inactiveForm);
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
        $plan = Plan::factory()->create(['limits_json' => ['branches' => 50, 'users' => 50]]);
        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_ends_at' => now('UTC')->addMonth(),
        ]);
        $tenant->update(['current_subscription_id' => $subscription->id]);

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

    private function makeActivationReady(Branch $branch): void
    {
        $branch->forceFill([
            'code' => 'READY-'.$branch->id,
            'capacity' => 20,
            'timezone' => 'Africa/Cairo',
            'currency' => 'EGP',
            'tax_rate_bps' => 1400,
            'tax_mode' => 'exclusive',
            'receipt_prefix' => 'PN',
            'payment_methods' => ['cash'],
        ])->save();
        foreach (range(1, 7) as $weekday) {
            DB::table('branch_opening_hours')->updateOrInsert(
                ['tenant_id' => $branch->tenant_id, 'branch_id' => $branch->id, 'weekday' => $weekday],
                [
                    'opens_at' => $weekday === 1 ? '09:00' : null,
                    'closes_at' => $weekday === 1 ? '18:00' : null,
                    'is_closed' => $weekday !== 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
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
