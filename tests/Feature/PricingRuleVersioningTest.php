<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CustomRole;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PricingRuleVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_versions_current_rule_and_snapshots_current_branch_tax(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'PLAY-60');
        $branch->update(['tax_rate_bps' => 1450, 'tax_mode' => 'inclusive']);

        $response = $this->actingAs($owner)
            ->postJson(route('pricing.versions.store', $rule), $this->payload([
                'name' => 'Extended play',
                'base_duration_minutes' => 90,
                'base_price_egp' => '125.5',
                'overtime_price_egp' => '75.05',
                'expected_version' => 1,
            ]))
            ->assertCreated()
            ->assertJsonStructure(['message', 'pricing_rule_id', 'version']);

        $newRule = PricingRule::query()->findOrFail($response->json('pricing_rule_id'));
        $this->assertSame(2, $response->json('version'));
        $this->assertSame('PLAY-60', $newRule->code);
        $this->assertSame('Extended play', $newRule->name);
        $this->assertSame(5400, $newRule->base_duration_seconds);
        $this->assertSame(12550, $newRule->base_price_minor);
        $this->assertSame(600, $newRule->grace_period_seconds);
        $this->assertSame(1800, $newRule->overtime_unit_seconds);
        $this->assertSame(7505, $newRule->overtime_price_minor);
        $this->assertSame('fixed_duration', $newRule->billing_mode);
        $this->assertSame('EGP', $newRule->currency);
        $this->assertSame(1450, $newRule->tax_rate_bps);
        $this->assertSame('inclusive', $newRule->tax_mode);
        $this->assertSame('active', $newRule->status);
        $this->assertSame($owner->id, $newRule->created_by_user_id);
        $this->assertSame('retired', $rule->fresh()->status);

        $audit = DB::table('audit_logs')->where('action', 'pricing.rule.versioned')->sole();
        $this->assertSame($tenant->id, $audit->tenant_id);
        $this->assertSame($branch->id, $audit->branch_id);
        $this->assertSame($owner->id, $audit->actor_user_id);
        $this->assertSame('pricing_rule', $audit->subject_type);
        $this->assertSame((string) $newRule->id, $audit->subject_id);
        $this->assertEquals([
            'old_pricing_rule_id' => (string) $rule->id,
            'new_pricing_rule_id' => (string) $newRule->id,
            'old_version' => 1,
            'new_version' => 2,
        ], json_decode($audit->after_json, true, 512, JSON_THROW_ON_ERROR));
        $this->assertNull($audit->before_json);
        $this->assertStringNotContainsString('Extended play', (string) $audit->after_json);
        $this->assertStringNotContainsString('12550', (string) $audit->after_json);
    }

    public function test_branch_manager_can_version_assigned_branch_but_view_only_roles_cannot(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->staff($tenant);
        $branch = $this->branch($tenant);
        $this->assign($tenant, $manager, $branch, 'branch_manager');
        $rule = $this->rule($tenant, $branch, $manager, 'MANAGER');

        $this->actingAs($manager)
            ->postJson(route('pricing.versions.store', $rule), $this->payload(['expected_version' => 1]))
            ->assertCreated();

        foreach (['reception_staff', 'reception', 'cashier'] as $role) {
            $staff = $this->staff($tenant);
            $this->assign($tenant, $staff, $branch, $role);
            $current = PricingRule::query()
                ->where('tenant_id', $tenant->id)
                ->where('branch_id', $branch->id)
                ->where('code', 'MANAGER')
                ->where('status', 'active')
                ->firstOrFail();

            $this->actingAs($staff)
                ->postJson(route('pricing.versions.store', $current), $this->payload(['expected_version' => 2]))
                ->assertForbidden();
        }

        $this->assertSame(2, PricingRule::query()->where('code', 'MANAGER')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'pricing.rule.versioned')->count());
    }

    public function test_custom_branches_view_role_cannot_version(): void
    {
        $tenant = Tenant::factory()->create();
        $branch = $this->branch($tenant);
        $staff = $this->staff($tenant);
        $customRole = CustomRole::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pricing viewer',
            'code' => 'pricing_viewer',
        ]);
        $customRole->permissions()->create(['permission' => 'branches.view']);
        $this->assign($tenant, $staff, $branch, $customRole->code);
        $rule = $this->rule($tenant, $branch, $staff, 'CUSTOM');

        $this->actingAs($staff)
            ->postJson(route('pricing.versions.store', $rule), $this->payload(['expected_version' => 1]))
            ->assertForbidden();

        $this->assertSame('active', $rule->fresh()->status);
        $this->assertSame(0, DB::table('audit_logs')->where('action', '!=', 'security.request_denied')->count());
    }

    public function test_stale_replay_and_retired_targets_conflict_without_writes(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'REPLAY');

        $this->actingAs($owner)
            ->postJson(route('pricing.versions.store', $rule), $this->payload(['expected_version' => 1]))
            ->assertCreated();
        $current = PricingRule::query()
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branch->id)
            ->where('code', 'REPLAY')
            ->where('status', 'active')
            ->firstOrFail();

        $this->actingAs($owner)
            ->postJson(route('pricing.versions.store', $rule), $this->payload(['expected_version' => 1]))
            ->assertConflict();
        $this->actingAs($owner)
            ->postJson(route('pricing.versions.store', $current), $this->payload(['expected_version' => 1]))
            ->assertConflict();
        $this->actingAs($owner)
            ->postJson(route('pricing.versions.store', $current), $this->payload(['expected_version' => 99]))
            ->assertConflict();

        $this->assertSame(2, PricingRule::query()->where('tenant_id', $tenant->id)->where('code', 'REPLAY')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'pricing.rule.versioned')->count());
        $this->assertSame('retired', $rule->fresh()->status);
        $this->assertSame('active', $current->fresh()->status);
    }

    public function test_foreign_rule_and_unmanaged_branch_are_denied_without_writes(): void
    {
        [$tenant, $owner] = $this->owner();
        $ownBranch = $this->branch($tenant, 'Own branch');
        $unmanagedBranch = $this->branch($tenant, 'Unmanaged branch');
        $foreignTenant = Tenant::factory()->create();
        $foreignBranch = $this->branch($foreignTenant, 'Foreign branch');
        $foreignActor = $this->staff($foreignTenant);
        $foreignRule = $this->rule($foreignTenant, $foreignBranch, $foreignActor, 'FOREIGN');
        $unmanagedRule = $this->rule($tenant, $unmanagedBranch, $owner, 'UNMANAGED');
        $manager = $this->staff($tenant);
        $this->assign($tenant, $manager, $ownBranch, 'branch_manager');

        $this->actingAs($owner)
            ->postJson(route('pricing.versions.store', $foreignRule), $this->payload(['expected_version' => 1]))
            ->assertNotFound();
        $this->actingAs($manager)
            ->postJson(route('pricing.versions.store', $unmanagedRule), $this->payload(['expected_version' => 1]))
            ->assertForbidden();

        $this->assertSame('active', $foreignRule->fresh()->status);
        $this->assertSame('active', $unmanagedRule->fresh()->status);
        $this->assertSame(0, DB::table('audit_logs')->where('action', '!=', 'security.request_denied')->count());
    }

    public function test_invalid_version_input_supports_json_and_html_without_writes(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'INVALID');
        $invalid = $this->payload([
            'name' => 'x',
            'base_duration_minutes' => 0,
            'base_price_egp' => '1.234',
            'overtime_price_egp' => '-1',
            'expected_version' => 0,
        ]);

        $this->actingAs($owner)
            ->postJson(route('pricing.versions.store', $rule), $invalid)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'base_duration_minutes', 'base_price_egp', 'overtime_price_egp', 'expected_version']);
        $this->actingAs($owner)
            ->from(route('pricing.index'))
            ->post(route('pricing.versions.store', $rule), $invalid)
            ->assertRedirect(route('pricing.index'))
            ->assertSessionHasErrors(['name', 'base_duration_minutes', 'base_price_egp', 'overtime_price_egp', 'expected_version']);

        $this->assertSame(1, PricingRule::query()->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_version_audit_failure_rolls_back_retirement_and_new_rule(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $rule = $this->rule($tenant, $branch, $owner, 'ROLLBACK');
        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert') && str_contains($query->sql, 'audit_logs')) {
                throw new RuntimeException('audit failure');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($owner)
                ->postJson(route('pricing.versions.store', $rule), $this->payload(['expected_version' => 1]));
            $this->fail('The audit failure should have escaped the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $this->assertSame(1, PricingRule::query()->where('tenant_id', $tenant->id)->where('code', 'ROLLBACK')->count());
        $this->assertSame('active', $rule->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Updated package',
            'base_duration_minutes' => 60,
            'base_price_egp' => '150.00',
            'overtime_price_egp' => '50.00',
            'expected_version' => 1,
        ], $overrides);
    }

    private function rule(Tenant $tenant, Branch $branch, User $actor, string $code): PricingRule
    {
        return PricingRule::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $actor->id,
            'code' => $code,
            'name' => $code.' package',
        ]);
    }

    private function assign(Tenant $tenant, User $user, Branch $branch, string $role): void
    {
        $user->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function branch(Tenant $tenant, string $name = 'Pricing branch'): Branch
    {
        return Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => $name, 'is_active' => true]);
    }

    private function staff(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $owner = $this->staff($tenant);
        DB::table('tenant_owners')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $owner];
    }
}
