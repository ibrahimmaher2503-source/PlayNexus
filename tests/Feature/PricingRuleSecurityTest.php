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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PricingRuleSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_lists_only_active_rules_in_all_active_own_branches(): void
    {
        [$tenant, $owner] = $this->owner();
        $ownBranch = $this->branch($tenant, 'Own branch');
        $inactiveBranch = $this->branch($tenant, 'Inactive branch');
        $inactiveBranch->update(['is_active' => false]);
        $foreignTenant = Tenant::factory()->create();
        $foreignBranch = $this->branch($foreignTenant, 'Foreign branch');
        $ownRule = $this->rule($tenant, $ownBranch, $owner, 'OWN');
        $inactiveRule = $this->rule($tenant, $inactiveBranch, $owner, 'INACTIVE');
        $this->rule($foreignTenant, $foreignBranch, User::factory()->create(['tenant_id' => $foreignTenant->id]), 'FOREIGN');

        $this->assertTrue(Gate::forUser($owner)->allows('view', $ownRule));
        $this->assertFalse(Gate::forUser($owner)->allows('view', $inactiveRule));

        $this->actingAs($owner)
            ->get(route('pricing.index'))
            ->assertOk()
            ->assertViewIs('pricing.index')
            ->assertViewHas('tenant', fn (Tenant $viewTenant): bool => $viewTenant->is($tenant))
            ->assertViewHas('branches', fn ($branches): bool => $branches->pluck('id')->all() === [$ownBranch->id])
            ->assertViewHas('rules', fn ($rules): bool => $rules->pluck('id')->all() === [$ownRule->id])
            ->assertViewHas('canManage', true)
            ->assertSee('OWN')
            ->assertDontSee('INACTIVE')
            ->assertDontSee('FOREIGN');
    }

    public function test_branch_manager_is_scoped_to_assigned_active_branch_and_can_create_there(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->staff($tenant);
        $assigned = $this->branch($tenant, 'Assigned');
        $viewOnly = $this->branch($tenant, 'View only');
        $unassigned = $this->branch($tenant, 'Unassigned');
        $this->assign($tenant, $manager, $assigned, 'branch_manager');
        $this->assign($tenant, $manager, $viewOnly, 'cashier');
        $this->rule($tenant, $assigned, $manager, 'VISIBLE');
        $this->rule($tenant, $viewOnly, $manager, 'VIEW-ONLY');
        $this->rule($tenant, $unassigned, $manager, 'HIDDEN');

        $this->assertTrue(Gate::forUser($manager)->allows('viewAny', PricingRule::class));
        $this->assertTrue(Gate::forUser($manager)->allows('create', new PricingRule(['branch_id' => $assigned->id])));

        $this->actingAs($manager)
            ->get(route('pricing.index'))
            ->assertOk()
            ->assertViewHas('branches', fn ($branches): bool => $branches->pluck('id')->all() === [$assigned->id, $viewOnly->id])
            ->assertViewHas('manageableBranches', fn ($branches): bool => $branches->pluck('id')->all() === [$assigned->id])
            ->assertSee('VISIBLE')
            ->assertSee('VIEW-ONLY')
            ->assertDontSee('HIDDEN')
            ->assertViewHas('canManage', true);

        $this->actingAs($manager)
            ->postJson(route('pricing.store'), $this->payload(['branch_id' => $unassigned->id, 'code' => 'DENIED']))
            ->assertForbidden();
        $this->actingAs($manager)
            ->postJson(route('pricing.store'), $this->payload(['branch_id' => $viewOnly->id, 'code' => 'VIEW-DENIED']))
            ->assertForbidden();

        $this->assertDatabaseMissing('pricing_rules', ['tenant_id' => $tenant->id, 'code' => 'DENIED']);
    }

    public function test_fixed_view_roles_can_read_but_cannot_create_and_custom_branch_view_is_denied(): void
    {
        $tenant = Tenant::factory()->create();
        $branch = $this->branch($tenant);
        $this->rule($tenant, $branch, User::factory()->create(['tenant_id' => $tenant->id]), 'VISIBLE');

        foreach (['reception_staff', 'reception', 'cashier'] as $role) {
            $staff = $this->staff($tenant);
            $this->assign($tenant, $staff, $branch, $role);

            $this->assertTrue(Gate::forUser($staff)->allows('viewAny', PricingRule::class));
            $this->actingAs($staff)->get(route('pricing.index'))->assertOk()->assertSee('VISIBLE');
            $this->actingAs($staff)
                ->postJson(route('pricing.store'), $this->payload(['branch_id' => $branch->id, 'code' => 'VIEW-'.Str::upper(str_replace('_', '-', $role))]))
                ->assertForbidden();
        }

        $custom = $this->staff($tenant);
        $customRole = CustomRole::create(['tenant_id' => $tenant->id, 'name' => 'Pricing viewer', 'code' => 'pricing_viewer']);
        $customRole->permissions()->create(['permission' => 'branches.view']);
        $this->assign($tenant, $custom, $branch, $customRole->code);

        $this->assertFalse(Gate::forUser($custom)->allows('viewAny', PricingRule::class));
        $this->actingAs($custom)->get(route('pricing.index'))->assertForbidden();
        $this->actingAs($custom)
            ->postJson(route('pricing.store'), $this->payload(['branch_id' => $branch->id, 'code' => 'CUSTOM']))
            ->assertForbidden();
    }

    public function test_owner_can_create_fixed_rule_with_exact_minor_units_and_tax_snapshot(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $branch->update(['tax_rate_bps' => 1450, 'tax_mode' => 'inclusive']);

        $response = $this->actingAs($owner)
            ->postJson(route('pricing.store'), $this->payload([
                'branch_id' => $branch->id,
                'code' => '  standard-60  ',
                'name' => '  Standard package  ',
                'base_duration_minutes' => '60',
                'base_price_egp' => '123.4',
                'overtime_price_egp' => '0.05',
            ]))
            ->assertCreated()
            ->assertJsonStructure(['message', 'pricing_rule_id']);

        $rule = PricingRule::query()->findOrFail($response->json('pricing_rule_id'));
        $this->assertSame('STANDARD-60', $rule->code);
        $this->assertSame('Standard package', $rule->name);
        $this->assertSame(3600, $rule->base_duration_seconds);
        $this->assertSame(12340, $rule->base_price_minor);
        $this->assertSame(600, $rule->grace_period_seconds);
        $this->assertSame(1800, $rule->overtime_unit_seconds);
        $this->assertSame(5, $rule->overtime_price_minor);
        $this->assertSame('fixed_duration', $rule->billing_mode);
        $this->assertSame('EGP', $rule->currency);
        $this->assertSame(1450, $rule->tax_rate_bps);
        $this->assertSame('inclusive', $rule->tax_mode);
        $this->assertSame('active', $rule->status);
        $this->assertSame(1, $rule->version);
        $this->assertSame($owner->id, $rule->created_by_user_id);

        $audit = DB::table('audit_logs')->where('action', 'pricing.rule.created')->sole();
        $this->assertSame($tenant->id, $audit->tenant_id);
        $this->assertSame($branch->id, $audit->branch_id);
        $this->assertSame($owner->id, $audit->actor_user_id);
        $this->assertSame('pricing_rule', $audit->subject_type);
        $this->assertSame((string) $rule->id, $audit->subject_id);
        $this->assertSame([
            'pricing_rule_id' => (string) $rule->id,
        ], json_decode($audit->after_json, true, 512, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('STANDARD-60', (string) $audit->after_json);
        $this->assertStringNotContainsString('Standard package', (string) $audit->after_json);
        $this->assertStringNotContainsString('12340', (string) $audit->after_json);
    }

    public function test_invalid_input_returns_json_and_html_validation_errors_without_writes(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $invalid = $this->payload([
            'branch_id' => $branch->id,
            'code' => 'bad code',
            'name' => 'x',
            'base_duration_minutes' => 0,
            'base_price_egp' => '1.234',
            'overtime_price_egp' => '-1',
        ]);

        $this->actingAs($owner)
            ->postJson(route('pricing.store'), $invalid)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'base_duration_minutes', 'base_price_egp', 'overtime_price_egp']);

        $this->actingAs($owner)
            ->from(route('pricing.index'))
            ->post(route('pricing.store'), $invalid)
            ->assertRedirect(route('pricing.index'))
            ->assertSessionHasErrors(['code', 'name', 'base_duration_minutes', 'base_price_egp', 'overtime_price_egp']);

        $this->assertDatabaseCount('pricing_rules', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_duplicate_code_is_a_conflict_without_rule_or_audit_write(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $this->rule($tenant, $branch, $owner, 'DUPLICATE');

        $this->actingAs($owner)
            ->postJson(route('pricing.store'), $this->payload(['branch_id' => $branch->id, 'code' => 'duplicate']))
            ->assertConflict()
            ->assertJsonPath('message', trans('pricing.conflict'));

        $this->assertSame(1, PricingRule::query()->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_foreign_or_inactive_branch_is_not_accepted(): void
    {
        [$tenant, $owner] = $this->owner();
        $inactive = $this->branch($tenant);
        $inactive->update(['is_active' => false]);
        $foreignTenant = Tenant::factory()->create();
        $foreign = $this->branch($foreignTenant);

        foreach ([$inactive, $foreign] as $branch) {
            $this->actingAs($owner)
                ->postJson(route('pricing.store'), $this->payload(['branch_id' => $branch->id]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('branch_id');
        }

        $this->assertDatabaseCount('pricing_rules', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_rolls_back_rule_creation(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert') && str_contains($query->sql, 'audit_logs')) {
                throw new RuntimeException('audit failure');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($owner)
                ->postJson(route('pricing.store'), $this->payload(['branch_id' => $branch->id]));
            $this->fail('The audit failure should have escaped the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $this->assertSame(0, PricingRule::query()->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_pricing_audit_action_is_visible_to_owner_with_id_only_snapshot(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $this->actingAs($owner)
            ->postJson(route('pricing.store'), $this->payload(['branch_id' => $branch->id, 'code' => 'AUDIT']))
            ->assertCreated();
        $rule = PricingRule::query()->where('code', 'AUDIT')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('audit.index', ['action' => 'pricing.rule.created']))
            ->assertOk()
            ->assertSee(trans('audit.actions')['pricing.rule.created'])
            ->assertSee(trans('audit.snapshot_key')['pricing_rule_id'])
            ->assertSee((string) $rule->id)
            ->assertDontSee('AUDIT')
            ->assertDontSee($rule->name);

        $this->assertSame($tenant->id, $rule->tenant_id);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'branch_id' => 1,
            'code' => 'STANDARD',
            'name' => 'Standard package',
            'base_duration_minutes' => 60,
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
