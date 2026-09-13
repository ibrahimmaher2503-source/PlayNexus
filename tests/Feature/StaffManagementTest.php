<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_scoped_staff_and_update_an_existing_account(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Status Target',
            'status' => 'active',
        ]);
        $foreign = User::factory()->create(['name' => 'Hidden Foreign Staff']);

        $this->actingAs($owner)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertViewIs('staff.index')
            ->assertSee($target->name)
            ->assertDontSee($foreign->name)
            ->assertSee(__('staff.owner_locked'))
            ->assertSee('name="locale" value="ar"', false)
            ->assertSee(__('Switch to :language', ['language' => __('Arabic')]))
            ->assertDontSee('id="locale"', false)
            ->assertDontSee('name="reason_code"', false)
            ->assertSee(__('staff.save'));

        $this->actingAs($owner)
            ->patch(route('staff.status', $target), [
                'status' => 'suspended',
                'expected_status' => 'active',
                'reason_code' => 'client-value-is-ignored',
            ])
            ->assertRedirect(route('staff.index'))
            ->assertSessionHas('success', __('staff.updated'));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'tenant_id' => $tenant->id,
            'status' => 'suspended',
        ]);

        $audit = DB::table('audit_logs')->where('subject_id', (string) $target->id)->sole();
        $this->assertSame($tenant->id, $audit->tenant_id);
        $this->assertSame($owner->id, $audit->actor_user_id);
        $this->assertSame('user', $audit->actor_type);
        $this->assertSame('staff.status.changed', $audit->action);
        $this->assertSame('user', $audit->subject_type);
        $this->assertSame('success', $audit->outcome);
        $this->assertSame('staffing_change', $audit->reason_code);
        $this->assertSame(['status' => 'active'], json_decode($audit->before_json, true));
        $this->assertSame(['status' => 'suspended'], json_decode($audit->after_json, true));
        $this->assertNotEmpty($audit->request_id);
    }

    public function test_staff_without_owner_access_cannot_view_or_update(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $target = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($staff)
            ->get(route('staff.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->patch(route('staff.status', $target), [
                'status' => 'disabled',
                'expected_status' => 'active',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'active']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_owner_targets_and_self_are_hidden_from_status_mutation(): void
    {
        [$tenant, $owner] = $this->owner();
        $secondOwner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert($this->ownerRow($tenant, $secondOwner));

        $this->actingAs($owner)
            ->patch(route('staff.status', $secondOwner), [])
            ->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('staff.status', $owner), [])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $owner->id, 'status' => 'active']);
        $this->assertDatabaseHas('users', ['id' => $secondOwner->id, 'status' => 'active']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_foreign_target_is_hidden_before_validation(): void
    {
        [$tenant, $owner] = $this->owner();
        $foreignTenant = Tenant::factory()->create();
        $foreign = User::factory()->create(['tenant_id' => $foreignTenant->id]);

        $this->actingAs($owner)
            ->patch(route('staff.status', $foreign), [])
            ->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $foreign->id, 'status' => 'active']);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertTrue($tenant->is_active);
    }

    public function test_invalid_status_and_expected_state_are_localized_and_do_not_write(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->from(route('staff.index'))
            ->withSession(['locale' => 'ar'])
            ->patch(route('staff.status', $target), [
                'status' => 'invited',
                'expected_status' => 'unknown',
            ])
            ->assertRedirect(route('staff.index'))
            ->assertSessionHasErrors([
                'status' => trans('staff.validation.status_invalid', [], 'ar'),
                'expected_status' => trans('staff.validation.expected_status_invalid', [], 'ar'),
            ]);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'active']);
        $this->assertDatabaseCount('audit_logs', 0);

        $this->actingAs($owner)
            ->patchJson(route('staff.status', $target), ['status' => 'invited'])
            ->assertUnprocessable();
    }

    public function test_validation_rerender_keeps_each_staff_row_on_its_persisted_status(): void
    {
        [$tenant, $owner] = $this->owner();
        $activeTarget = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $suspendedTarget = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'suspended']);

        $this->actingAs($owner)
            ->from(route('staff.index'))
            ->patch(route('staff.status', $activeTarget), [
                'status' => 'invited',
                'expected_status' => 'invalid',
            ])
            ->assertRedirect(route('staff.index'));

        $html = $this->actingAs($owner)->get(route('staff.index'))->getContent();
        $activeSelect = $this->selectMarkup($html, $activeTarget->id);
        $suspendedSelect = $this->selectMarkup($html, $suspendedTarget->id);

        $this->assertStringContainsString('value="active" selected', $activeSelect);
        $this->assertStringNotContainsString('value="suspended" selected', $activeSelect);
        $this->assertStringContainsString('value="suspended" selected', $suspendedSelect);
        $this->assertStringNotContainsString('value="active" selected', $suspendedSelect);
    }

    public function test_stale_expected_status_returns_conflict_without_audit(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        User::query()->whereKey($target->id)->update(['status' => 'suspended']);

        $this->actingAs($owner)
            ->patch(route('staff.status', $target), [
                'status' => 'disabled',
                'expected_status' => 'active',
            ])
            ->assertStatus(409)
            ->assertSee(__('staff.conflict'));

        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'suspended']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_noop_is_successful_without_audit(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);

        $this->actingAs($owner)
            ->patch(route('staff.status', $target), [
                'status' => 'active',
                'expected_status' => 'active',
            ])
            ->assertRedirect(route('staff.index'))
            ->assertSessionHas('status_message', __('staff.no_change'));

        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'active']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_rolls_back_the_status_update(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);

        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            $sql = strtolower(trim($query->sql));
            if (str_starts_with($sql, 'insert') && str_contains($sql, 'audit_logs')) {
                throw new RuntimeException('staff audit failure');
            }
        });

        try {
            $this->actingAs($owner)
                ->patch(route('staff.status', $target), [
                    'status' => 'disabled',
                    'expected_status' => 'active',
                ])
                ->assertStatus(500);
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'active']);
    }

    public function test_disabled_target_loses_an_existing_session_on_next_protected_request(): void
    {
        [$tenant, $owner] = $this->owner();
        $target = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $target->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->patch(route('staff.status', $target), [
                'status' => 'disabled',
                'expected_status' => 'active',
            ])
            ->assertRedirect(route('staff.index'));

        $this->actingAs($target)
            ->withSession(['branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('branch_id');

        $this->assertGuest();
    }

    public function test_staff_list_is_tenant_scoped_and_paginated_with_localized_page_shell(): void
    {
        [$tenant, $owner] = $this->owner();
        User::factory()->count(30)->create(['tenant_id' => $tenant->id]);
        $foreign = Tenant::factory()->create();
        $foreignUser = User::factory()->create(['tenant_id' => $foreign->id]);
        $expectedIds = User::query()->where('tenant_id', $tenant->id)->orderBy('id')->pluck('id')->all();

        $this->actingAs($owner)
            ->withSession(['locale' => 'ar'])
            ->get(route('staff.index', ['page' => 2, 'tenant_id' => $foreign->id]))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertViewHas('staff', function (LengthAwarePaginator $staff) use ($expectedIds, $foreignUser): bool {
                $this->assertSame(count($expectedIds), $staff->total());
                $this->assertSame(25, $staff->perPage());
                $this->assertSame(2, $staff->currentPage());
                $this->assertSame(array_slice($expectedIds, 25), $staff->getCollection()->modelKeys());
                $this->assertNotContains($foreignUser->id, $staff->getCollection()->modelKeys());

                return true;
            });
    }

    public function test_owner_can_search_staff_by_name_or_email_and_open_branch_access_directly(): void
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
        $foreignTenant = Tenant::factory()->create();
        $foreign = User::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'name' => 'Alice Foreign',
            'email' => 'alice-foreign@example.test',
        ]);

        $this->actingAs($owner)
            ->get(route('staff.index', ['q' => '  Alice  ']))
            ->assertOk()
            ->assertViewHas('search', 'Alice')
            ->assertSee($nameMatch->name)
            ->assertSee('href="'.route('assignments.index', ['user_id' => $nameMatch->id]).'"', false)
            ->assertDontSee($emailMatch->name)
            ->assertDontSee($other->name)
            ->assertDontSee($foreign->name)
            ->assertSee(__('staff.search_label'))
            ->assertSee('role="search"', false);

        $this->get(route('staff.index', ['q' => 'bob@example.test']))
            ->assertOk()
            ->assertSee($emailMatch->name)
            ->assertDontSee($nameMatch->name);

        $this->get(route('staff.index', ['q' => str_repeat('A', 120)]))
            ->assertOk()
            ->assertViewHas('search', str_repeat('A', 100));
    }

    public function test_staff_search_has_a_localized_no_result_state(): void
    {
        [$tenant, $owner] = $this->owner();
        User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Existing staff']);

        foreach ([['en', 'No staff accounts match this search.'], ['ar', 'لا توجد حسابات موظفين تطابق هذا البحث.']] as [$locale, $message]) {
            $this->actingAs($owner)->withSession(['locale' => $locale])
                ->get(route('staff.index', ['q' => 'does-not-exist']))
                ->assertOk()
                ->assertSee($message)
                ->assertSee('for="staff-search"', false)
                ->assertSee('id="staff-search"', false);
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

    private function selectMarkup(string $html, int $userId): string
    {
        $start = strpos($html, 'id="status-'.$userId.'"');
        $this->assertNotFalse($start);
        $end = strpos($html, '</select>', $start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }
}
