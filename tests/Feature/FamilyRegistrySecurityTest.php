<?php

namespace Tests\Feature;

use App\Http\Controllers\FamilyController;
use App\Models\Branch;
use App\Models\Child;
use App\Models\CustomRole;
use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Tests\TestCase;

class FamilyRegistrySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_normalizer_handles_egyptian_and_generic_e164_values(): void
    {
        $this->assertSame('+201001112233', PhoneNormalizer::normalize('0100 111 2233'));
        $this->assertSame('+201001112233', PhoneNormalizer::normalize('+20 100 111 2233'));
        $this->assertSame('+201001112233', PhoneNormalizer::normalize('0020-100-111-2233'));
        $this->assertSame('+14155552671', PhoneNormalizer::normalize('+1 (415) 555-2671'));
        $this->assertNull(PhoneNormalizer::normalize('0111234567'));
    }

    public function test_active_owner_can_create_without_a_branch_assignment(): void
    {
        [$tenant, $owner] = $this->owner();

        $this->assertTrue(Gate::forUser($owner)->allows('create', Guardian::class));

        $this->actingAs($owner)
            ->postJson(route('families.store'), $this->payload())
            ->assertCreated()
            ->assertJsonStructure(['guardian_id', 'child_id']);

        $this->assertDatabaseHas('guardians', [
            'tenant_id' => $tenant->id,
            'phone_e164' => '+201001112233',
        ]);
    }

    public function test_fixed_active_branch_roles_can_access_but_custom_branches_view_cannot(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        foreach (['branch_manager', 'reception_staff', 'reception', 'cashier'] as $role) {
            $staff = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
            $staff->branches()->attach($branch, [
                'tenant_id' => $tenant->id,
                'role' => $role,
                'is_active' => true,
            ]);

            $this->assertTrue(Gate::forUser($staff)->allows('viewAny', Guardian::class));
        }

        $custom = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $customRole = CustomRole::create([
            'tenant_id' => $tenant->id,
            'name' => 'Branch viewer',
            'code' => 'branch_viewer',
        ]);
        $customRole->permissions()->create(['permission' => 'branches.view']);
        $custom->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => $customRole->code,
            'is_active' => true,
        ]);

        $this->assertFalse(Gate::forUser($custom)->allows('viewAny', Guardian::class));
        $this->actingAs($custom)->postJson(route('families.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('guardians', 0);

        // Keep the owner referenced so the fixture always proves same-tenant setup.
        $this->assertSame($tenant->id, $owner->tenant_id);
    }

    public function test_inactive_assignment_or_branch_is_denied(): void
    {
        [$tenant] = $this->owner();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $staff->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => 'cashier',
            'is_active' => false,
        ]);

        $this->assertFalse(Gate::forUser($staff)->allows('viewAny', Guardian::class));
        $this->actingAs($staff)->postJson(route('families.store'), $this->payload())->assertForbidden();

        DB::table('branch_user')->where('branch_id', $branch->id)->update(['is_active' => true]);
        $branch->update(['is_active' => false]);

        $this->assertFalse(Gate::forUser($staff->fresh())->allows('viewAny', Guardian::class));
        $this->assertSame(0, DB::table('guardians')->count());
    }

    public function test_same_tenant_normalized_phone_returns_conflict_without_writes(): void
    {
        [$tenant, $owner] = $this->owner();
        Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'phone_e164' => '+201001112233',
        ]);

        $this->actingAs($owner)
            ->postJson(route('families.store'), $this->payload(['phone' => '0020 100 111 2233']))
            ->assertConflict()
            ->assertJsonPath('existing_guardian_id', fn ($id): bool => is_int($id) || is_string($id));

        $this->actingAs($owner)
            ->from(route('families.create'))
            ->post(route('families.store'), $this->payload())
            ->assertRedirect(route('families.create'))
            ->assertSessionHas('existing_family_url');

        $this->assertSame(1, DB::table('guardians')->count());
        $this->assertSame(0, DB::table('children')->count());
        $this->assertSame(0, DB::table('guardian_child')->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_search_is_current_tenant_scoped_and_matches_phone_or_child_name(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'phone_e164' => '+201001112233',
            'full_name' => 'Own guardian',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $child = Child::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Own child',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $this->link($tenant, $owner, $guardian, $child);

        $foreignTenant = Tenant::factory()->create();
        $foreignGuardian = Guardian::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'full_name' => 'Foreign private guardian',
            'phone_e164' => '+201009998877',
        ]);
        $foreignChild = Child::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'full_name' => 'Foreign private child',
        ]);
        $this->link($foreignTenant, $foreignGuardian->createdBy, $foreignGuardian, $foreignChild);

        foreach (['0100 111 2233', 'Own child'] as $query) {
            $this->actingAs($owner)
                ->get(route('families.index', ['q' => $query]))
                ->assertOk()
                ->assertViewHas('families', function ($families) use ($guardian): bool {
                    return $families->count() === 1 && $families->first()->is($guardian);
                })
                ->assertSee('Own guardian')
                ->assertDontSee('Foreign private guardian')
                ->assertDontSee('Foreign private child');
        }

        $this->actingAs($owner)
            ->get(route('families.index', ['q' => str_repeat('x', 150)]))
            ->assertViewHas('search', fn (string $search): bool => strlen($search) === 100);
    }

    public function test_search_excludes_inactive_guardians_and_children(): void
    {
        [$tenant, $owner] = $this->owner();
        $inactiveGuardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Inactive guardian',
            'phone_e164' => '+201001112233',
            'status' => 'inactive',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $activeGuardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Active guardian',
            'phone_e164' => '+201009998877',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $inactiveChild = Child::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Inactive child',
            'status' => 'inactive',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $this->link($tenant, $owner, $activeGuardian, $inactiveChild);

        $this->actingAs($owner)
            ->get(route('families.index', ['q' => '0100 111 2233']))
            ->assertOk()
            ->assertViewHas('families', fn ($families): bool => $families->isEmpty())
            ->assertDontSee($inactiveGuardian->full_name);
        $this->actingAs($owner)
            ->get(route('families.index', ['q' => 'Inactive child']))
            ->assertOk()
            ->assertViewHas('families', fn ($families): bool => $families->isEmpty())
            ->assertDontSee($activeGuardian->full_name);
    }

    public function test_foreign_tenant_phone_is_not_disclosed_or_treated_as_duplicate(): void
    {
        [$tenant, $owner] = $this->owner();
        $foreignTenant = Tenant::factory()->create();
        Guardian::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'phone_e164' => '+201001112233',
            'full_name' => 'Foreign private guardian',
        ]);

        $response = $this->actingAs($owner)
            ->postJson(route('families.store'), $this->payload())
            ->assertCreated();

        $this->assertDatabaseHas('guardians', [
            'tenant_id' => $tenant->id,
            'phone_e164' => '+201001112233',
        ]);
        $response->assertJsonMissing(['full_name' => 'Foreign private guardian']);
    }

    public function test_registration_creates_only_safe_audit_ids_atomically(): void
    {
        [$tenant, $owner] = $this->owner();

        $response = $this->actingAs($owner)
            ->postJson(route('families.store'), $this->payload([
                'guardian_name' => '  Parent One  ',
                'email' => ' PARENT@EXAMPLE.TEST ',
                'date_of_birth' => now()->toDateString(),
                'relationship_type' => 'parent',
            ]))
            ->assertCreated();

        $guardianId = $response->json('guardian_id');
        $childId = $response->json('child_id');
        $this->assertDatabaseHas('guardian_child', [
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardianId,
            'child_id' => $childId,
            'relationship_type' => 'legal_guardian',
            'is_active' => 1,
        ]);

        $audit = DB::table('audit_logs')->where('action', 'family.created')->sole();
        $after = json_decode($audit->after_json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals([
            'guardian_id' => (string) $guardianId,
            'child_id' => (string) $childId,
        ], $after);
        $this->assertSame($owner->id, $audit->actor_user_id);
        $this->assertSame($tenant->id, $audit->tenant_id);
        $this->assertSame('family_registration', $audit->reason_code);
    }

    public function test_audit_failure_rolls_back_the_entire_family(): void
    {
        [, $owner] = $this->owner();
        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert') && str_contains($query->sql, 'audit_logs')) {
                throw new RuntimeException('audit failure');
            }
        });

        try {
            $this->withoutExceptionHandling()->actingAs($owner)->postJson(route('families.store'), $this->payload());
            $this->fail('The audit failure should escape the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseCount('guardians', 0);
        $this->assertDatabaseCount('children', 0);
        $this->assertDatabaseCount('guardian_child', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'guardian_name' => 'Parent One',
            'phone' => '01001112233',
            'email' => null,
            'preferred_locale' => 'en',
            'child_name' => 'Child One',
            'date_of_birth' => null,
            'relationship_type' => 'parent',
            'child_data_consent' => '1',
            'notice_version' => FamilyController::NOTICE_VERSION,
        ], $overrides);
    }

    private function link(Tenant $tenant, User $actor, Guardian $guardian, Child $child): void
    {
        DB::table('guardian_child')->insert([
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'relationship_type' => 'parent',
            'is_active' => true,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $owner];
    }
}
