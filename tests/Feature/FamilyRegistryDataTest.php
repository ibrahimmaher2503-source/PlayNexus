<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FamilyRegistryDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_records_are_tenant_scoped_and_linked_with_an_active_relation(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Nadia Guardian',
            'phone_e164' => '+201012345678',
            'preferred_locale' => 'en',
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
        $child = Child::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Mina Child',
            'date_of_birth' => '2020-05-06',
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);

        DB::table('guardian_child')->insert([
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'relationship_type' => 'mother',
            'is_active' => true,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $guardian->load('children');
        $child->load('guardians');

        $this->assertTrue($tenant->guardians()->whereKey($guardian)->exists());
        $this->assertTrue($tenant->children()->whereKey($child)->exists());
        $this->assertTrue($guardian->children->contains($child));
        $this->assertTrue($child->guardians->contains($guardian));
        $this->assertSame('mother', $guardian->children->first()->pivot->relationship_type);
        $this->assertTrue((bool) $guardian->children->first()->pivot->is_active);
        $this->assertSame('+20••••5678', $guardian->maskedPhone());
        $this->assertSame('2020-05-06', $child->date_of_birth->format('Y-m-d'));
    }

    public function test_factories_create_valid_tenant_aware_records_by_default(): void
    {
        $guardian = Guardian::factory()->create();
        $child = Child::factory()->create(['tenant_id' => $guardian->tenant_id]);

        $this->assertDatabaseHas('users', [
            'id' => $guardian->created_by_user_id,
            'tenant_id' => $guardian->tenant_id,
        ]);
        $this->assertSame($guardian->tenant_id, $child->tenant_id);
    }

    public function test_schema_has_required_columns_indexes_and_no_deferred_family_data(): void
    {
        foreach (['tenant_id', 'full_name', 'phone_e164', 'email', 'preferred_locale', 'status', 'created_by_user_id', 'updated_by_user_id', 'lock_version'] as $column) {
            $this->assertTrue(Schema::hasColumn('guardians', $column), "Missing guardians.$column");
        }

        foreach (['tenant_id', 'full_name', 'date_of_birth', 'emergency_contact_name', 'emergency_contact_phone_e164', 'safety_notes_encrypted', 'status', 'created_by_user_id', 'updated_by_user_id', 'lock_version'] as $column) {
            $this->assertTrue(Schema::hasColumn('children', $column), "Missing children.$column");
        }

        foreach (['tenant_id', 'guardian_id', 'child_id', 'relationship_type', 'can_consent', 'can_check_out', 'is_primary', 'verification_method', 'verified_at', 'verified_by_user_id', 'is_active', 'revoked_at', 'revoked_by_user_id', 'created_by_user_id', 'updated_by_user_id'] as $column) {
            $this->assertTrue(Schema::hasColumn('guardian_child', $column), "Missing guardian_child.$column");
        }

        $phoneIndex = collect(Schema::getIndexes('guardians'))->first(fn (array $index): bool => $index['columns'] === ['tenant_id', 'phone_e164']);
        $this->assertNotNull($phoneIndex);
        $this->assertTrue($phoneIndex['unique']);
        $this->assertTrue(collect(Schema::getIndexes('children'))->contains(fn (array $index): bool => $index['columns'] === ['tenant_id', 'full_name']));
        $this->assertFalse(Schema::hasColumn('guardians', 'operational_consent_at'));
        $this->assertTrue(Schema::hasTable('family_consent_events'));
        $this->assertFalse(Schema::hasColumn('children', 'photo_object_key'));
    }

    public function test_cross_tenant_guardian_child_and_actor_references_are_rejected(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        [$foreignTenant, $foreignActor] = $this->tenantActor();
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
        $foreignChild = Child::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'created_by_user_id' => $foreignActor->id,
            'updated_by_user_id' => $foreignActor->id,
        ]);

        $this->expectException(QueryException::class);
        DB::table('guardian_child')->insert([
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardian->id,
            'child_id' => $foreignChild->id,
            'relationship_type' => 'other',
            'is_active' => true,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cross_tenant_guardian_actor_reference_is_rejected(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        [, $foreignActor] = $this->tenantActor();

        $this->expectException(QueryException::class);
        Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $foreignActor->id,
            'updated_by_user_id' => $actor->id,
        ]);
    }

    public function test_duplicate_guardian_child_relation_is_rejected(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
        $child = Child::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
        $row = [
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'relationship_type' => 'legal_guardian',
            'is_active' => true,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('guardian_child')->insert($row);

        $this->expectException(QueryException::class);
        DB::table('guardian_child')->insert($row);
    }

    /** @return array{Tenant, User} */
    private function tenantActor(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);

        return [$tenant, $actor];
    }
}
