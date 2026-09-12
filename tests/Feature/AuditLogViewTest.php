<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLogViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_successful_logs_with_batched_labels_and_safe_snapshots(): void
    {
        [$tenant, $owner] = $this->owner();
        $actor = $this->user($tenant, 'Audit Actor');
        $branch = $this->branch($tenant, 'Main Branch');
        $malicious = '<script>alert("snapshot")</script>';
        $this->audit($tenant, $actor, $branch, before: [
            'status' => $malicious,
            'email' => 'private@example.test',
            'nested' => ['html' => '<img src=x>'],
        ], after: [
            'status' => 'active',
            'role' => 'cashier',
            'is_active' => true,
            'branch_id' => $branch->id,
            'name' => 'Visible snapshot name',
            'unknown' => '<svg onload=alert(1)>',
        ]);

        $response = $this->actingAs($owner)->get(route('audit.index'));

        $response->assertOk()
            ->assertViewIs('audit.index')
            ->assertSee($tenant->name)
            ->assertSee($actor->name)
            ->assertSee($branch->name)
            ->assertSee(trans('audit.actions')['staff.status.changed'])
            ->assertSee(trans('audit.reasons.staffing_change'))
            ->assertSee(trans('audit.outcomes.success'))
            ->assertSee('Visible snapshot name')
            ->assertSee(e($malicious), false)
            ->assertDontSee($malicious, false)
            ->assertDontSee('private@example.test')
            ->assertDontSee('nested')
            ->assertDontSee('unknown')
            ->assertSee(trans('audit.snapshot_boolean.true'));
    }

    public function test_non_owner_cannot_view_audit_logs(): void
    {
        [$tenant, $owner] = $this->owner();
        $staff = $this->user($tenant, 'Tenant Staff');
        $this->audit($tenant, $owner, null);

        $this->actingAs($staff)
            ->get(route('audit.index'))
            ->assertForbidden();
    }

    public function test_request_tenant_ids_are_ignored_and_foreign_rows_are_hidden(): void
    {
        [$tenant, $owner] = $this->owner();
        $ownActor = $this->user($tenant, 'Own Actor');
        $ownBranch = $this->branch($tenant, 'Own Branch');
        $foreignTenant = Tenant::factory()->create(['name' => 'Foreign Tenant Must Stay Hidden']);
        $foreignActor = $this->user($foreignTenant, 'Foreign Actor Must Stay Hidden');
        $foreignBranch = $this->branch($foreignTenant, 'Foreign Branch Must Stay Hidden');
        $this->audit($tenant, $ownActor, $ownBranch);
        $this->audit($foreignTenant, $foreignActor, $foreignBranch);

        $this->actingAs($owner)
            ->get(route('audit.index', ['tenant_id' => $foreignTenant->id, 'id' => $foreignTenant->id]))
            ->assertOk()
            ->assertViewHas('tenant', fn (Tenant $viewTenant): bool => $viewTenant->is($tenant))
            ->assertSee($ownActor->name)
            ->assertSee($ownBranch->name)
            ->assertDontSee($foreignTenant->name)
            ->assertDontSee($foreignActor->name)
            ->assertDontSee($foreignBranch->name);
    }

    public function test_action_and_actor_filters_are_scoped_and_only_success_is_shown(): void
    {
        [$tenant, $owner] = $this->owner();
        $actor = $this->user($tenant, 'Filtered Actor');
        $otherActor = $this->user($tenant, 'Other Actor');
        $branch = $this->branch($tenant);
        $this->audit($tenant, $actor, $branch, action: 'branch.created');
        $this->audit($tenant, $actor, $branch, action: 'staff.status.changed');
        $this->audit($tenant, $otherActor, $branch, action: 'branch.created', outcome: 'failure');

        $this->actingAs($owner)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee($actor->name)
            ->assertDontSee($otherActor->name);

        $this->actingAs($owner)
            ->get(route('audit.index', ['action' => 'branch.created', 'actor_user_id' => $actor->id]))
            ->assertOk()
            ->assertSee(trans('audit.actions')['branch.created'])
            ->assertSee($actor->name)
            ->assertDontSee('Other Actor')
            ->assertViewHas('logs', function (LengthAwarePaginator $logs): bool {
                $this->assertSame(1, $logs->total());
                $this->assertSame('branch.created', $logs->first()->action);

                return true;
            });

        $this->getJson(route('audit.index', ['action' => 'not-allowed']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action']);

        $this->getJson(route('audit.index', ['outcome' => 'failure']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['outcome']);
    }

    public function test_settings_events_are_named_and_filterable(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $this->audit($tenant, $owner, null, action: 'tenant.profile.updated', reasonCode: 'setup_change');
        $this->audit($tenant, $owner, $branch, action: 'branch.settings.updated', reasonCode: 'setup_change');

        foreach (['tenant.profile.updated', 'branch.settings.updated'] as $action) {
            $this->actingAs($owner)
                ->get(route('audit.index', ['action' => $action]))
                ->assertOk()
                ->assertSee(trans('audit.actions')[$action])
                ->assertSee(trans('audit.reasons.setup_change'))
                ->assertDontSee(trans('audit.unknown_action'))
                ->assertDontSee(trans('audit.unknown_reason'))
                ->assertViewHas('logs', fn (LengthAwarePaginator $logs): bool => $logs->total() === 1 && $logs->first()->action === $action);
        }
    }

    public function test_foreign_and_nonexistent_actor_filters_have_the_same_validation_error(): void
    {
        [$tenant, $owner] = $this->owner();
        $foreignTenant = Tenant::factory()->create();
        $foreignActor = $this->user($foreignTenant, 'Foreign Actor');
        $this->actingAs($owner);

        $foreign = $this->getJson(route('audit.index', ['actor_user_id' => $foreignActor->id]));
        $missing = $this->getJson(route('audit.index', ['actor_user_id' => 99999999]));

        $foreign->assertUnprocessable()->assertJsonValidationErrors(['actor_user_id']);
        $missing->assertUnprocessable()->assertJsonValidationErrors(['actor_user_id']);
        $this->assertSame(
            $foreign->json('errors.actor_user_id.0'),
            $missing->json('errors.actor_user_id.0'),
        );
    }

    public function test_logs_are_newest_first_and_paginated_by_25_with_exact_selected_fields(): void
    {
        [$tenant, $owner] = $this->owner();
        $actor = $this->user($tenant, 'Paging Actor');
        $branch = $this->branch($tenant);
        $firstOccurredAt = Carbon::now('UTC')->subSeconds(29);

        for ($i = 0; $i < 30; $i++) {
            $this->audit(
                $tenant,
                $actor,
                $branch,
                occurredAt: $firstOccurredAt->copy()->addSeconds($i),
                after: ['status' => 'active'],
            );
        }

        $this->actingAs($owner)
            ->get(route('audit.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('logs', function (LengthAwarePaginator $logs): bool {
                $this->assertSame(30, $logs->total());
                $this->assertSame(25, $logs->perPage());
                $this->assertSame(2, $logs->currentPage());
                $this->assertCount(5, $logs->getCollection());

                $fields = array_keys(get_object_vars($logs->first()));
                sort($fields);
                $expected = [
                    'action',
                    'actor_user_id',
                    'after_json',
                    'before_json',
                    'branch_id',
                    'id',
                    'occurred_at',
                    'outcome',
                    'reason_code',
                    'request_id',
                    'subject_id',
                    'subject_type',
                ];
                sort($expected);
                $this->assertSame($expected, $fields);

                return true;
            });
    }

    public function test_arabic_audit_page_is_rtl_and_localizes_labels(): void
    {
        [$tenant, $owner] = $this->owner();
        $actor = $this->user($tenant, 'Arabic Actor');
        $branch = $this->branch($tenant, 'Arabic Branch');
        $this->audit($tenant, $actor, $branch, action: 'staff.branch_assignment.changed', reasonCode: 'access_review');

        $this->actingAs($owner)
            ->withSession(['locale' => 'ar'])
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee(trans('audit.page_title', [], 'ar'))
            ->assertSee(trans('audit.actions', [], 'ar')['staff.branch_assignment.changed'])
            ->assertSee(trans('audit.reasons.access_review', [], 'ar'))
            ->assertSee($branch->name);
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->user($tenant, 'Tenant Owner');
        DB::table('tenant_owners')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $owner];
    }

    private function user(Tenant $tenant, string $name): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function branch(Tenant $tenant, string $name = 'Audit Branch'): Branch
    {
        return Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function audit(
        Tenant $tenant,
        User $actor,
        ?Branch $branch,
        string $action = 'staff.status.changed',
        string $outcome = 'success',
        string $reasonCode = 'staffing_change',
        ?array $before = null,
        ?array $after = null,
        ?Carbon $occurredAt = null,
    ): void {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch?->id,
            'actor_user_id' => $actor->id,
            'actor_type' => 'user',
            'action' => $action,
            'subject_type' => 'user',
            'subject_id' => (string) $actor->id,
            'outcome' => $outcome,
            'reason_code' => $reasonCode,
            'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_json' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'request_id' => (string) Str::uuid(),
            'occurred_at' => $occurredAt ?? Carbon::now('UTC'),
        ]);
    }
}
