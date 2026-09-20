<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CustomRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class BranchAssignmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_grant_change_revoke_and_leave_audit_trail_without_deleting_pivot(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = $this->staff($tenant);
        $branch = $this->branch($tenant);
        $this->actingAs($owner);

        $this->put(route('assignments.update', [$target, $branch]), [
            'role' => 'reception_staff',
            'is_active' => '1',
            'reason_code' => 'staffing_change',
            'expected_role' => null,
            'expected_is_active' => null,
        ])->assertRedirect(route('assignments.index', ['user_id' => $target->id]));

        $this->assertDatabaseHas('branch_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $target->id,
            'branch_id' => $branch->id,
            'role' => 'reception_staff',
            'is_active' => 1,
        ]);
        $this->assertSame(1, DB::table('branch_user')->count());

        $this->putJson(route('assignments.update', [$target, $branch]), [
            'role' => 'cashier',
            'is_active' => false,
            'reason_code' => 'access_review',
            'expected_role' => 'reception_staff',
            'expected_is_active' => true,
        ])->assertOk()->assertJsonPath('changed', true);

        $this->assertDatabaseHas('branch_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $target->id,
            'branch_id' => $branch->id,
            'role' => 'cashier',
            'is_active' => 0,
        ]);

        $this->putJson(route('assignments.update', [$target, $branch]), [
            'role' => 'cashier',
            'is_active' => false,
            'reason_code' => 'correction',
            'expected_role' => 'cashier',
            'expected_is_active' => false,
        ])->assertOk()->assertJsonPath('changed', false);

        $this->assertSame(2, DB::table('audit_logs')->count());
        $audit = DB::table('audit_logs')->orderBy('id')->first();
        $this->assertSame('staff.branch_assignment.changed', $audit->action);
        $this->assertSame('user', $audit->actor_type);
        $this->assertSame((string) $target->id, $audit->subject_id);
        $this->assertSame($branch->id, $audit->branch_id);
        $this->assertSame('access_review', $audit->reason_code);
        $this->assertNull($audit->before_json);
        $this->assertEqualsCanonicalizing(['branch_id' => $branch->id, 'role' => 'reception_staff', 'is_active' => true], json_decode($audit->after_json, true));
        $this->assertStringNotContainsString($target->email, (string) $audit->before_json.(string) $audit->after_json);
    }

    public function test_legacy_reception_is_readable_but_cannot_be_granted_or_selected_as_a_new_role(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = $this->staff($tenant);
        $branch = $this->branch($tenant);
        $target->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => 'reception',
            'is_active' => true,
        ]);
        $this->actingAs($owner);

        $this->get(route('assignments.index', ['user_id' => $target->id]))
            ->assertOk()
            ->assertSee(trans('assignments.roles.reception'));

        $this->putJson(route('assignments.update', [$target, $branch]), [
            'role' => 'reception',
            'is_active' => true,
            'reason_code' => 'correction',
            'expected_role' => 'reception',
            'expected_is_active' => true,
        ])->assertUnprocessable();
    }

    public function test_owner_page_uses_tenant_scoped_staff_picker_and_active_branch_rows(): void
    {
        [$tenant, $owner] = $this->owner();
        $staff = User::factory()->count(30)->create(['tenant_id' => $tenant->id]);
        $selected = $staff->last();
        $branch = $this->branch($tenant, 'Active branch');
        $inactive = $this->branch($tenant, 'Inactive branch');
        $inactive->update(['is_active' => false]);
        $foreignTenant = Tenant::factory()->create();
        $foreignStaff = $this->staff($foreignTenant, 'Foreign staff');
        $this->actingAs($owner);

        $this->get(route('assignments.index'))
            ->assertOk()
            ->assertViewIs('assignments.index')
            ->assertViewHas('staff', function (LengthAwarePaginator $paginator): bool {
                $this->assertSame(30, $paginator->total());
                $this->assertSame(25, $paginator->perPage());
                $fields = array_keys($paginator->first()->getAttributes());
                sort($fields);
                $this->assertSame(['email', 'id', 'name', 'status'], $fields);

                return true;
            })
            ->assertDontSee($foreignStaff->name);

        $this->get(route('assignments.index', ['user_id' => $selected->id]))
            ->assertOk()
            ->assertViewHas('selectedUser', fn (User $viewUser): bool => $viewUser->is($selected))
            ->assertViewHas('branches', fn ($branches): bool => $branches->pluck('id')->all() === [$branch->id])
            ->assertSee($selected->name)
            ->assertSee('Active branch')
            ->assertDontSee('name="reason_code"', false)
            ->assertDontSee('Inactive branch');
    }

    public function test_owner_can_search_staff_by_trimmed_name_or_email_with_safe_wildcards(): void
    {
        [$tenant, $owner] = $this->owner();
        $nameMatch = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Alice Example',
            'email' => 'alice@example.test',
        ]);
        $emailMatch = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Bob Example',
            'email' => 'bob@example.test',
        ]);
        $other = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Charlie Example',
            'email' => 'charlie@example.test',
        ]);
        $wildcardMatch = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Literal% percent',
            'email' => 'literal-percent@example.test',
        ]);
        $foreignTenant = Tenant::factory()->create();
        $foreignMatch = User::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'name' => 'Alice Foreign',
            'email' => 'alice-foreign@example.test',
        ]);

        $this->actingAs($owner)
            ->get(route('assignments.index', ['q' => '  Alice  ']))
            ->assertOk()
            ->assertViewHas('search', 'Alice')
            ->assertSee($nameMatch->name)
            ->assertDontSee($emailMatch->name)
            ->assertDontSee($other->name)
            ->assertDontSee($foreignMatch->name);

        $this->get(route('assignments.index', ['q' => 'bob@example.test']))
            ->assertOk()
            ->assertSee($emailMatch->name)
            ->assertDontSee($nameMatch->name);

        $this->get(route('assignments.index', ['q' => '%']))
            ->assertOk()
            ->assertSee($wildcardMatch->name)
            ->assertDontSee($other->name);
    }

    public function test_staff_search_is_trimmed_capped_and_preserves_selection_and_pagination_query(): void
    {
        [$tenant, $owner] = $this->owner();
        $staff = User::factory()->count(30)->create([
            'tenant_id' => $tenant->id,
            'name' => 'Searchable staff',
        ]);
        $selected = $staff->last();
        $this->actingAs($owner);

        $response = $this->get(route('assignments.index', [
            'q' => '  Searchable staff  ',
            'user_id' => $selected->id,
        ]));

        $response->assertOk()
            ->assertViewHas('search', 'Searchable staff')
            ->assertSee('value="'.$selected->id.'" selected', false)
            ->assertSee('maxlength="100"', false)
            ->assertSee('q=Searchable%20staff', false)
            ->assertSee('user_id='.$selected->id, false);

        $this->get(route('assignments.index', ['q' => str_repeat('A', 120)]))
            ->assertOk()
            ->assertViewHas('search', str_repeat('A', 100));
    }

    public function test_staff_search_empty_state_is_localized_and_keeps_an_accessible_form(): void
    {
        [$tenant, $owner] = $this->owner();
        $this->staff($tenant, 'Existing staff');

        foreach ([['en', 'No staff accounts match this search.'], ['ar', 'لا توجد حسابات موظفين تطابق هذا البحث.']] as [$locale, $message]) {
            $this->actingAs($owner)->withSession(['locale' => $locale])
                ->get(route('assignments.index', ['q' => 'does-not-exist']))
                ->assertOk()
                ->assertSee($message)
                ->assertSee('role="search"', false)
                ->assertSee('for="q"', false)
                ->assertSee('id="q"', false);
        }
    }

    public function test_non_owner_actor_cannot_read_or_write_assignments(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->staff($tenant);
        $target = $this->staff($tenant);
        $branch = $this->branch($tenant);

        $this->actingAs($actor)
            ->get(route('assignments.index'))
            ->assertForbidden();

        $this->putJson(route('assignments.update', [$target, $branch]), [
            'role' => 'cashier',
            'is_active' => true,
            'reason_code' => 'staffing_change',
            'expected_role' => null,
            'expected_is_active' => null,
        ])->assertForbidden();
    }

    public function test_branch_manager_can_manage_reception_and_cashier_only_in_managed_branches(): void
    {
        [$tenant, $owner] = $this->owner();
        $manager = $this->staff($tenant, 'Scoped manager');
        $managed = $this->branch($tenant, 'Managed branch');
        $unassigned = $this->branch($tenant, 'Unassigned branch');
        $inactive = $this->branch($tenant, 'Inactive branch');
        $inactive->update(['is_active' => false]);
        $manager->branches()->attach($managed, [
            'tenant_id' => $tenant->id,
            'role' => 'branch_manager',
            'is_active' => true,
        ]);

        $reception = $this->staff($tenant, 'Managed reception');
        $reception->branches()->attach($managed, [
            'tenant_id' => $tenant->id,
            'role' => 'reception_staff',
            'is_active' => true,
        ]);
        $cashier = $this->staff($tenant, 'Managed cashier');
        $cashier->branches()->attach($managed, [
            'tenant_id' => $tenant->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
        $otherStaff = $this->staff($tenant, 'Other branch staff');
        $otherStaff->branches()->attach($unassigned, [
            'tenant_id' => $tenant->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
        $managerTarget = $this->staff($tenant, 'Another manager');
        $managerTarget->branches()->attach($managed, [
            'tenant_id' => $tenant->id,
            'role' => 'branch_manager',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('assignments.index'))
            ->assertOk()
            ->assertSee($reception->name)
            ->assertSee($cashier->name)
            ->assertDontSee($otherStaff->name)
            ->assertDontSee($managerTarget->name)
            ->assertDontSee($owner->name);

        $this->putJson(route('assignments.update', [$reception, $managed]), [
            'role' => 'cashier',
            'is_active' => true,
            'expected_role' => 'reception_staff',
            'expected_is_active' => true,
        ])->assertOk()->assertJsonPath('changed', true);

        $this->putJson(route('assignments.update', [$reception, $unassigned]), [
            'role' => 'cashier',
            'is_active' => true,
            'expected_role' => null,
            'expected_is_active' => null,
        ])->assertNotFound();

        $this->putJson(route('assignments.update', [$cashier, $inactive]), [
            'role' => 'cashier',
            'is_active' => true,
            'expected_role' => null,
            'expected_is_active' => null,
        ])->assertNotFound();

        $this->putJson(route('assignments.update', [$reception, $managed]), [
            'role' => 'branch_manager',
            'is_active' => true,
            'expected_role' => 'cashier',
            'expected_is_active' => true,
        ])->assertForbidden();

        $this->putJson(route('assignments.update', [$managerTarget, $managed]), [
            'role' => 'cashier',
            'is_active' => true,
            'expected_role' => 'branch_manager',
            'expected_is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseHas('branch_user', [
            'tenant_id' => $tenant->id,
            'branch_id' => $managed->id,
            'user_id' => $reception->id,
            'role' => 'cashier',
            'is_active' => 1,
        ]);
    }

    public function test_owner_and_foreign_targets_or_branches_are_not_manageable(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = $this->staff($tenant);
        $branch = $this->branch($tenant);
        $foreignTenant = Tenant::factory()->create();
        $foreignUser = $this->staff($foreignTenant);
        $foreignBranch = $this->branch($foreignTenant);
        $this->actingAs($owner);
        $payload = [
            'role' => 'cashier',
            'is_active' => true,
            'reason_code' => 'staffing_change',
            'expected_role' => null,
            'expected_is_active' => null,
        ];

        $this->putJson(route('assignments.update', [$foreignUser, $branch]), $payload)->assertNotFound();
        $this->putJson(route('assignments.update', [$target, $foreignBranch]), $payload)->assertNotFound();
        $this->putJson(route('assignments.update', [$owner, $branch]), $payload)->assertForbidden();
    }

    public function test_inactive_branch_is_hidden_and_inactive_target_is_a_conflict(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = $this->staff($tenant);
        $branch = $this->branch($tenant);
        $branch->update(['is_active' => false]);
        $this->actingAs($owner);
        $payload = [
            'role' => 'cashier',
            'is_active' => true,
            'reason_code' => 'staffing_change',
            'expected_role' => null,
            'expected_is_active' => null,
        ];

        $this->get(route('assignments.index', ['user_id' => $target->id]))
            ->assertOk()
            ->assertDontSee($branch->name);
        $this->putJson(route('assignments.update', [$target, $branch]), $payload)->assertNotFound();

        User::query()->whereKey($target->id)->update(['status' => 'suspended']);
        $activeBranch = $this->branch($tenant);
        $this->putJson(route('assignments.update', [$target, $activeBranch]), $payload)->assertStatus(409);
        $this->assertDatabaseMissing('branch_user', ['user_id' => $target->id, 'branch_id' => $activeBranch->id]);
    }

    public function test_invalid_roles_and_missing_expected_state_are_rejected_without_mutation(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = $this->staff($tenant);
        $branch = $this->branch($tenant);
        $this->actingAs($owner);

        $this->putJson(route('assignments.update', [$target, $branch]), [
            'role' => 'game_operator',
            'is_active' => true,
            'reason_code' => 'staffing_change',
            'expected_role' => null,
            'expected_is_active' => null,
        ])->assertUnprocessable()->assertJsonValidationErrors(['role']);

        $this->putJson(route('assignments.update', [$target, $branch]), [
            'role' => 'cashier',
            'is_active' => true,
            'reason_code' => 'staffing_change',
        ])->assertUnprocessable()->assertJsonValidationErrors(['expected_role', 'expected_is_active']);

        $this->assertDatabaseMissing('branch_user', ['user_id' => $target->id, 'branch_id' => $branch->id]);
    }

    public function test_owner_can_assign_only_custom_roles_that_grant_branch_view_in_the_same_tenant(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = $this->staff($tenant);
        $branch = $this->branch($tenant);
        $role = CustomRole::create(['tenant_id' => $tenant->id, 'name' => 'Floor lead', 'code' => 'floor_lead']);
        $role->permissions()->create(['permission' => 'branches.view']);
        $blockedRole = CustomRole::create(['tenant_id' => $tenant->id, 'name' => 'No access', 'code' => 'no_access']);
        $foreignRole = CustomRole::create(['tenant_id' => Tenant::factory()->create()->id, 'name' => 'Foreign', 'code' => 'foreign_role']);

        $this->actingAs($owner)
            ->get(route('assignments.index', ['user_id' => $target->id]))
            ->assertOk()
            ->assertSee('Floor lead')
            ->assertDontSee('No access')
            ->assertDontSee('Foreign');

        $this->putJson(route('assignments.update', [$target, $branch]), [
            'role' => $role->code,
            'is_active' => true,
            'expected_role' => null,
            'expected_is_active' => null,
        ])->assertOk();
        $this->assertDatabaseHas('branch_user', ['tenant_id' => $tenant->id, 'role' => $role->code, 'is_active' => 1]);

        foreach ([$blockedRole->code, $foreignRole->code] as $code) {
            $this->putJson(route('assignments.update', [$target, $branch]), [
                'role' => $code,
                'is_active' => true,
                'expected_role' => $role->code,
                'expected_is_active' => true,
            ])->assertUnprocessable()->assertJsonValidationErrors('role');
        }
    }

    public function test_validation_error_does_not_copy_one_branch_form_state_into_another_row(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = $this->staff($tenant);
        $first = $this->branch($tenant, 'First branch');
        $second = $this->branch($tenant, 'Second branch');
        $target->branches()->attach($first, [
            'tenant_id' => $tenant->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
        $target->branches()->attach($second, [
            'tenant_id' => $tenant->id,
            'role' => 'branch_manager',
            'is_active' => true,
        ]);
        $url = route('assignments.index', ['user_id' => $target->id]);

        $this->actingAs($owner)
            ->from($url)
            ->put(route('assignments.update', [$target, $first]), [
                'role' => 'invalid',
                'is_active' => true,
                'reason_code' => 'staffing_change',
                'expected_role' => 'cashier',
                'expected_is_active' => true,
            ])
            ->assertRedirect($url);

        $this->get($url)
            ->assertOk()
            ->assertSee('value="cashier" selected', false)
            ->assertSee('value="branch_manager" selected', false)
            ->assertSee('value="cashier"', false)
            ->assertSee('value="branch_manager"', false);
    }

    public function test_stale_expected_role_or_state_returns_409_and_preserves_current_assignment(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = $this->staff($tenant);
        $branch = $this->branch($tenant);
        $target->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => 'branch_manager',
            'is_active' => true,
        ]);
        $this->actingAs($owner);

        $this->putJson(route('assignments.update', [$target, $branch]), [
            'role' => 'cashier',
            'is_active' => true,
            'reason_code' => 'access_review',
            'expected_role' => 'reception_staff',
            'expected_is_active' => true,
        ])->assertStatus(409);

        $this->assertDatabaseHas('branch_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $target->id,
            'branch_id' => $branch->id,
            'role' => 'branch_manager',
            'is_active' => 1,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_rolls_back_the_branch_mutation(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = $this->staff($tenant);
        $branch = $this->branch($tenant);
        $payload = [
            'role' => 'cashier',
            'is_active' => true,
            'reason_code' => 'staffing_change',
            'expected_role' => null,
            'expected_is_active' => null,
        ];
        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert') && str_contains($query->sql, 'audit_logs')) {
                throw new RuntimeException('audit failure');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($owner)
                ->putJson(route('assignments.update', [$target, $branch]), $payload);
            $this->fail('The audit failure should have escaped the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseMissing('branch_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $target->id,
            'branch_id' => $branch->id,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_revoked_assignment_cannot_keep_a_staff_branch_context_on_the_next_request(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = $this->staff($tenant);
        $branch = $this->branch($tenant);
        $target->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
        $this->actingAs($owner)->putJson(route('assignments.update', [$target, $branch]), [
            'role' => 'cashier',
            'is_active' => false,
            'reason_code' => 'access_review',
            'expected_role' => 'cashier',
            'expected_is_active' => true,
        ])->assertOk();

        $this->actingAs($target)->withSession(['branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionMissing('branch_id')
            ->assertDontSee($branch->name);
        $this->actingAs($target)->getJson('/branches/'.$branch->id)->assertNotFound();
    }

    public function test_english_and_arabic_assignment_pages_keep_direction_and_accessible_labels(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = $this->staff($tenant, 'Visible staff');
        $branch = $this->branch($tenant, 'Visible branch');

        foreach ([['en', 'ltr', 'Choose a staff account'], ['ar', 'rtl', 'اختر حساب موظف']] as [$locale, $direction, $label]) {
            $this->actingAs($owner)->withSession(['locale' => $locale])
                ->get(route('assignments.index', ['user_id' => $target->id]))
                ->assertOk()
                ->assertSee('lang="'.$locale.'"', false)
                ->assertSee('dir="'.$direction.'"', false)
                ->assertSee($label)
                ->assertSee($branch->name);
        }
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

    private function staff(Tenant $tenant, ?string $name = null): User
    {
        return User::factory()->create(array_filter([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'status' => 'active',
        ], static fn ($value): bool => $value !== null));
    }

    private function branch(Tenant $tenant, ?string $name = null): Branch
    {
        return Branch::factory()->create(array_filter([
            'tenant_id' => $tenant->id,
            'name' => $name,
        ], static fn ($value): bool => $value !== null));
    }
}
