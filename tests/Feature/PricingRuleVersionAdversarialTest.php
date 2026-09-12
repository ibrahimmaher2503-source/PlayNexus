<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PricingRuleVersionAdversarialTest extends TestCase
{
    use RefreshDatabase;

    public function test_replaying_expected_version_creates_at_most_one_new_rule_and_audit(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $target = $this->rule($tenant, $branch, $owner, 'REPLAY');
        $payload = $this->versionPayload(['expected_version' => 1]);

        $this->actingAs($owner)
            ->postJson(route('pricing.versions.store', $target), $payload)
            ->assertCreated();

        $this->actingAs($owner)
            ->postJson(route('pricing.versions.store', $target), $payload)
            ->assertConflict();

        $this->assertSame(2, PricingRule::query()->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('pricing_rules', [
            'id' => $target->id,
            'status' => 'retired',
            'version' => 1,
        ]);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'pricing.rule.versioned')->count());
    }

    public function test_retired_target_is_a_conflict_without_another_version_or_audit(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $target = $this->rule($tenant, $branch, $owner, 'RETIRED');
        $target->update(['status' => 'retired']);
        PricingRule::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'code' => $target->code,
            'version' => 2,
            'created_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->postJson(route('pricing.versions.store', $target), $this->versionPayload())
            ->assertConflict();

        $this->assertSame(2, PricingRule::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_foreign_target_is_hidden_without_a_write(): void
    {
        [$tenant, $owner] = $this->owner();
        $foreignTenant = Tenant::factory()->create();
        $foreignOwner = $this->staff($foreignTenant);
        $foreignBranch = $this->branch($foreignTenant);
        $foreignRule = $this->rule($foreignTenant, $foreignBranch, $foreignOwner, 'FOREIGN');

        $this->actingAs($owner)
            ->postJson(route('pricing.versions.store', $foreignRule), $this->versionPayload())
            ->assertNotFound();

        $this->assertSame(1, PricingRule::query()->where('tenant_id', $foreignTenant->id)->count());
        $this->assertDatabaseHas('pricing_rules', [
            'id' => $foreignRule->id,
            'tenant_id' => $foreignTenant->id,
            'status' => 'active',
            'version' => 1,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_reception_and_cashier_cannot_version_a_rule(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $target = $this->rule($tenant, $branch, $owner, 'STAFF');

        foreach (['reception_staff', 'reception', 'cashier'] as $role) {
            $staff = $this->staff($tenant);
            $this->assign($tenant, $staff, $branch, $role);

            $this->actingAs($staff)
                ->postJson(route('pricing.versions.store', $target), $this->versionPayload())
                ->assertForbidden();
        }

        $this->assertDatabaseHas('pricing_rules', [
            'id' => $target->id,
            'status' => 'active',
            'version' => 1,
        ]);
        $this->assertSame(1, PricingRule::query()->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_branch_manager_cannot_version_cashier_only_or_unassigned_branch(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $manager = $this->staff($tenant);
        $managed = $this->branch($tenant, 'Managed');
        $cashierOnly = $this->branch($tenant, 'Cashier only');
        $unassigned = $this->branch($tenant, 'Unassigned');
        $cashierRule = $this->rule($tenant, $cashierOnly, $manager, 'CASHIER-ONLY');
        $unassignedRule = $this->rule($tenant, $unassigned, $manager, 'UNASSIGNED');
        $this->assign($tenant, $manager, $managed, 'branch_manager');
        $this->assign($tenant, $manager, $cashierOnly, 'cashier');

        foreach ([$cashierRule, $unassignedRule] as $target) {
            $this->actingAs($manager)
                ->postJson(route('pricing.versions.store', $target), $this->versionPayload())
                ->assertForbidden();
        }

        $this->assertSame(2, PricingRule::query()->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('pricing_rules', ['id' => $cashierRule->id, 'status' => 'active']);
        $this->assertDatabaseHas('pricing_rules', ['id' => $unassignedRule->id, 'status' => 'active']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_rolls_back_retirement_and_new_rule(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $target = $this->rule($tenant, $branch, $owner, 'ROLLBACK');
        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            $sql = strtolower(trim($query->sql));
            if (str_starts_with($sql, 'insert') && str_contains($sql, 'audit_logs')) {
                throw new RuntimeException('audit failure');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($owner)
                ->postJson(route('pricing.versions.store', $target), $this->versionPayload());
            $this->fail('The audit failure should have escaped the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $target->refresh();
        $this->assertSame('active', $target->status);
        $this->assertSame(1, PricingRule::query()->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_versioning_preserves_old_values_and_snapshots_current_tax_with_exact_money(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $target = $this->rule($tenant, $branch, $owner, 'IMMUTABLE');
        $target->update([
            'name' => 'Legacy package',
            'base_duration_seconds' => 3600,
            'base_price_minor' => 11111,
            'grace_period_seconds' => 777,
            'overtime_unit_seconds' => 901,
            'overtime_price_minor' => 2222,
            'tax_rate_bps' => 1250,
            'tax_mode' => 'exclusive',
        ]);
        $branch->update(['tax_rate_bps' => 1450, 'tax_mode' => 'inclusive']);

        $response = $this->actingAs($owner)
            ->postJson(route('pricing.versions.store', $target), $this->versionPayload([
                'name' => 'Current package',
                'base_duration_minutes' => 67,
                'base_price_egp' => '123.4',
                'overtime_price_egp' => '0.05',
            ]))
            ->assertCreated();

        $newRule = PricingRule::query()->findOrFail($response->json('pricing_rule_id'));
        $target->refresh();

        $this->assertSame('retired', $target->status);
        $this->assertSame('Legacy package', $target->name);
        $this->assertSame(3600, $target->base_duration_seconds);
        $this->assertSame(11111, $target->base_price_minor);
        $this->assertSame(777, $target->grace_period_seconds);
        $this->assertSame(901, $target->overtime_unit_seconds);
        $this->assertSame(2222, $target->overtime_price_minor);
        $this->assertSame(1250, $target->tax_rate_bps);
        $this->assertSame('exclusive', $target->tax_mode);

        $this->assertSame($target->code, $newRule->code);
        $this->assertSame('Current package', $newRule->name);
        $this->assertSame(2, $newRule->version);
        $this->assertSame(4020, $newRule->base_duration_seconds);
        $this->assertSame(12340, $newRule->base_price_minor);
        $this->assertSame(5, $newRule->overtime_price_minor);
        $this->assertSame(1450, $newRule->tax_rate_bps);
        $this->assertSame('inclusive', $newRule->tax_mode);
        $this->assertSame('active', $newRule->status);
    }

    public function test_version_audit_contains_only_rule_ids_and_versions(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $target = $this->rule($tenant, $branch, $owner, 'AUDIT-V2');

        $response = $this->actingAs($owner)
            ->postJson(route('pricing.versions.store', $target), $this->versionPayload([
                'name' => 'Audit version name',
                'base_price_egp' => '9876.54',
                'overtime_price_egp' => '321.09',
            ]))
            ->assertCreated();
        $newRule = PricingRule::query()->findOrFail($response->json('pricing_rule_id'));
        $audit = DB::table('audit_logs')->where('action', 'pricing.rule.versioned')->sole();

        $this->assertSame([
            'old_pricing_rule_id' => (string) $target->id,
            'new_pricing_rule_id' => (string) $newRule->id,
            'old_version' => 1,
            'new_version' => 2,
        ], json_decode($audit->after_json, true, 512, JSON_THROW_ON_ERROR));
        $this->assertNull($audit->before_json);
        $this->assertSame((string) $newRule->id, $audit->subject_id);
        $this->assertStringNotContainsString('AUDIT-V2', $audit->after_json);
        $this->assertStringNotContainsString('Audit version name', $audit->after_json);
        $this->assertStringNotContainsString('987654', $audit->after_json);
        $this->assertStringNotContainsString('32109', $audit->after_json);
        $this->assertSame(1, DB::table('audit_logs')->where('tenant_id', $tenant->id)->count());
    }

    /** @param array<string, mixed> $overrides */
    private function versionPayload(array $overrides = []): array
    {
        return array_merge([
            'expected_version' => 1,
            'name' => 'Updated package',
            'base_duration_minutes' => 45,
            'base_price_egp' => '150.00',
            'overtime_price_egp' => '50.00',
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
