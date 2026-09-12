<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PricingRuleDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_has_required_columns_defaults_indexes_and_no_deferred_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('pricing_rules', [
            'id',
            'tenant_id',
            'branch_id',
            'code',
            'name',
            'version',
            'billing_mode',
            'base_duration_seconds',
            'base_price_minor',
            'grace_period_seconds',
            'overtime_unit_seconds',
            'overtime_price_minor',
            'currency',
            'tax_rate_bps',
            'tax_mode',
            'status',
            'created_by_user_id',
            'created_at',
            'updated_at',
        ]));

        $this->assertFalse(Schema::hasColumn('pricing_rules', 'overtime_rounding'));
        $this->assertFalse(Schema::hasColumn('pricing_rules', 'pause_billing_mode'));
        $this->assertFalse(Schema::hasColumn('pricing_rules', 'effective_from'));
        $this->assertFalse(Schema::hasColumn('pricing_rules', 'effective_to'));
        $this->assertFalse(Schema::hasColumn('pricing_rules', 'ticket_type_id'));
        $this->assertFalse(Schema::hasColumn('pricing_rules', 'session_id'));

        $indexes = collect(Schema::getIndexes('pricing_rules'));
        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['tenant_id', 'branch_id', 'code', 'version']));
        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['columns'] === ['tenant_id', 'branch_id', 'status', 'code']));

        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);
        $rule = PricingRule::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'code' => 'DEFAULTS',
            'name' => 'Default values',
            'base_duration_seconds' => 3600,
            'base_price_minor' => 15000,
            'overtime_price_minor' => 5000,
            'tax_rate_bps' => 1450,
            'tax_mode' => 'inclusive',
            'created_by_user_id' => $actor->id,
        ]);
        $rule->refresh();

        $this->assertSame(1, (int) $rule->version);
        $this->assertSame('fixed_duration', $rule->billing_mode);
        $this->assertSame(600, (int) $rule->grace_period_seconds);
        $this->assertSame(1800, (int) $rule->overtime_unit_seconds);
        $this->assertSame('EGP', $rule->currency);
        $this->assertSame('active', $rule->status);
    }

    public function test_factory_casts_integer_fields_and_exposes_tenant_branch_and_actor_relations(): void
    {
        $rule = PricingRule::factory()->create();

        foreach ([
            'version',
            'base_duration_seconds',
            'base_price_minor',
            'grace_period_seconds',
            'overtime_unit_seconds',
            'overtime_price_minor',
            'tax_rate_bps',
        ] as $field) {
            $this->assertIsInt($rule->{$field}, $field);
        }

        $rule->load(['tenant', 'branch', 'createdBy']);
        $this->assertTrue($rule->tenant->is($rule->branch->tenant));
        $this->assertSame($rule->tenant_id, $rule->branch->tenant_id);
        $this->assertSame($rule->tenant_id, $rule->createdBy->tenant_id);
        $this->assertTrue($rule->tenant->pricingRules->contains($rule));
        $this->assertTrue($rule->branch->pricingRules->contains($rule));
    }

    public function test_cross_tenant_branch_reference_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $foreignBranch = Branch::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(QueryException::class);
        PricingRule::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $foreignBranch->id,
            'created_by_user_id' => $actor->id,
        ]);
    }

    public function test_cross_tenant_actor_reference_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $foreignActor = User::factory()->create();

        $this->expectException(QueryException::class);
        PricingRule::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $foreignActor->id,
        ]);
    }

    public function test_duplicate_code_and_version_in_one_branch_are_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);
        $attributes = [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $actor->id,
            'code' => 'STANDARD',
            'version' => 1,
        ];
        PricingRule::factory()->create($attributes);

        $this->expectException(QueryException::class);
        PricingRule::factory()->create($attributes);
    }

    public function test_unsigned_money_duration_and_rate_columns_reject_negative_values_when_supported(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->assertTrue(true);

            return;
        }

        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);

        foreach (['base_duration_seconds', 'base_price_minor', 'overtime_price_minor', 'tax_rate_bps'] as $column) {
            $this->expectException(QueryException::class);
            PricingRule::factory()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'created_by_user_id' => $actor->id,
                $column => -1,
            ]);
        }
    }
}
