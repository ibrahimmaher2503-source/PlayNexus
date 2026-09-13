<?php

namespace Tests\Feature;

use App\Http\Controllers\FamilyController;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class FamilyProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_a_current_tenant_family_and_only_active_links(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner);
        $active = $this->child($tenant, $owner, 'Active child');
        $inactive = $this->child($tenant, $owner, 'Inactive child');
        $this->link($tenant, $owner, $guardian, $active, true);
        $this->link($tenant, $owner, $guardian, $inactive, false);

        $this->actingAs($owner)
            ->getJson(route('families.show', $guardian))
            ->assertOk()
            ->assertJsonPath('guardian.id', $guardian->id)
            ->assertJsonPath('children.0.id', $active->id)
            ->assertJsonMissing(['full_name' => 'Inactive child']);
    }

    public function test_guardian_update_normalizes_phone_increments_version_and_audits_only_fields(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner);

        $this->actingAs($owner)
            ->patchJson(route('families.update', $guardian), [
                'guardian_name' => 'Updated parent',
                'phone' => '0020 100 111 2233',
                'email' => 'UPDATED@EXAMPLE.TEST',
                'preferred_locale' => 'en',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('guardian.lock_version', 2);

        $this->assertDatabaseHas('guardians', [
            'id' => $guardian->id,
            'tenant_id' => $tenant->id,
            'full_name' => 'Updated parent',
            'phone_e164' => '+201001112233',
            'email' => 'updated@example.test',
            'lock_version' => 2,
        ]);

        $audit = DB::table('audit_logs')->where('action', 'family.guardian.updated')->sole();
        $json = $audit->after_json.$audit->before_json;
        $this->assertStringContainsString('guardian_id', $json);
        $this->assertStringContainsString('full_name', $json);
        $this->assertStringNotContainsString('Updated parent', $json);
        $this->assertStringNotContainsString('+201001112233', $json);
        $this->assertStringNotContainsString('updated@example.test', $json);
    }

    public function test_guardian_update_rejects_stale_version_and_same_tenant_duplicate_with_existing_url(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner);

        $this->actingAs($owner)
            ->patchJson(route('families.update', $guardian), [
                'guardian_name' => 'Stale parent',
                'phone' => '01001112233',
                'email' => null,
                'preferred_locale' => 'ar',
                'expected_version' => 2,
            ])
            ->assertStatus(409)
            ->assertJsonPath('current_version', 1);
        $this->assertDatabaseHas('guardians', ['id' => $guardian->id, 'full_name' => 'Parent One', 'lock_version' => 1]);

        $duplicate = $this->guardian($tenant, $owner, 'Other parent', '+201009998877');
        $response = $this->actingAs($owner)->patchJson(route('families.update', $guardian), [
            'guardian_name' => 'Parent One',
            'phone' => '01009998877',
            'email' => null,
            'preferred_locale' => 'ar',
            'expected_version' => 1,
        ]);
        $response->assertStatus(409)->assertJsonPath('existing_guardian_id', $duplicate->id);
        $this->assertSame(route('families.show', $duplicate), $response->json('existing_family_url'));
        $this->assertSame(1, (int) DB::table('guardians')->where('id', $guardian->id)->value('lock_version'));
    }

    public function test_child_update_requires_an_active_current_tenant_link_and_audits_changed_fields(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner);
        $child = $this->child($tenant, $owner, 'Child One');
        $this->link($tenant, $owner, $guardian, $child, true);

        $this->actingAs($owner)
            ->patchJson(route('families.children.update', [$guardian, $child]), [
                'child_name' => 'Child Updated',
                'date_of_birth' => '2018-01-02',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('child.lock_version', 2);

        $this->assertDatabaseHas('children', ['id' => $child->id, 'full_name' => 'Child Updated', 'date_of_birth' => '2018-01-02', 'lock_version' => 2]);
        $audit = DB::table('audit_logs')->where('action', 'family.child.updated')->sole();
        $this->assertStringContainsString('child_id', $audit->after_json);
        $this->assertStringContainsString('date_of_birth', $audit->after_json);
        $this->assertStringNotContainsString('2018-01-02', $audit->after_json);

        $this->patchJson(route('families.children.update', [$guardian, $child]), [
            'child_name' => 'Should not save',
            'date_of_birth' => null,
            'expected_version' => 1,
        ])->assertStatus(409);
        $this->assertDatabaseHas('children', ['id' => $child->id, 'full_name' => 'Child Updated', 'lock_version' => 2]);
    }

    public function test_new_child_is_created_with_active_allowlisted_relationship_and_safe_audit(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner);

        $response = $this->actingAs($owner)
            ->postJson(route('families.children.store', $guardian), [
                'child_name' => 'New Child',
                'date_of_birth' => '2017-05-06',
                'relationship_type' => 'mother',
                'expected_version' => 1,
                'child_data_consent' => '1',
                'notice_version' => FamilyController::NOTICE_VERSION,
            ])
            ->assertCreated();

        $childId = $response->json('child.id');
        $this->assertDatabaseHas('guardian_child', [
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardian->id,
            'child_id' => $childId,
            'relationship_type' => 'mother',
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('guardians', ['id' => $guardian->id, 'lock_version' => 2]);

        $this->actingAs($owner)
            ->postJson(route('families.children.store', $guardian), [
                'child_name' => 'Retry Must Not Save',
                'date_of_birth' => null,
                'relationship_type' => 'mother',
                'expected_version' => 1,
                'child_data_consent' => '1',
                'notice_version' => FamilyController::NOTICE_VERSION,
            ])
            ->assertStatus(409);
        $this->assertDatabaseMissing('children', ['full_name' => 'Retry Must Not Save']);
        $audit = DB::table('audit_logs')->where('action', 'family.child.added')->sole();
        $this->assertStringContainsString((string) $childId, $audit->after_json);
        $this->assertStringContainsString((string) $guardian->id, $audit->after_json);
        $this->assertStringNotContainsString('New Child', $audit->after_json);
        $this->assertStringNotContainsString('2017-05-06', $audit->after_json);
    }

    public function test_foreign_family_and_unassigned_staff_are_not_disclosed_or_mutable(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner);
        $foreignTenant = Tenant::factory()->create();
        $foreignOwner = User::factory()->create(['tenant_id' => $foreignTenant->id]);
        DB::table('tenant_owners')->insert(['tenant_id' => $foreignTenant->id, 'user_id' => $foreignOwner->id, 'created_at' => now(), 'updated_at' => now()]);
        $foreignGuardian = $this->guardian($foreignTenant, $foreignOwner, 'Foreign private parent');
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)->getJson(route('families.show', $foreignGuardian))->assertNotFound();
        $this->actingAs($staff)->getJson(route('families.show', $guardian))->assertForbidden();
        $this->assertDatabaseHas('guardians', ['id' => $foreignGuardian->id, 'full_name' => 'Foreign private parent']);
    }

    public function test_audit_failure_rolls_back_child_addition(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner);
        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert') && str_contains($query->sql, 'audit_logs')) {
                throw new RuntimeException('audit failure');
            }
        });

        try {
            $this->withoutExceptionHandling()->actingAs($owner)->postJson(route('families.children.store', $guardian), [
                'child_name' => 'Rollback Child',
                'date_of_birth' => null,
                'relationship_type' => 'parent',
                'expected_version' => 1,
                'child_data_consent' => '1',
                'notice_version' => FamilyController::NOTICE_VERSION,
            ]);
            $this->fail('The audit failure should escape the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $this->assertSame(0, (int) DB::table('children')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, (int) DB::table('guardian_child')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, (int) DB::table('audit_logs')->where('tenant_id', $tenant->id)->count());
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$tenant, $owner];
    }

    private function guardian(Tenant $tenant, User $actor, string $name = 'Parent One', string $phone = '+201001112233'): Guardian
    {
        return Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => $name,
            'phone_e164' => $phone,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
    }

    private function child(Tenant $tenant, User $actor, string $name): Child
    {
        return Child::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => $name,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
    }

    private function link(Tenant $tenant, User $actor, Guardian $guardian, Child $child, bool $active): void
    {
        DB::table('guardian_child')->insert([
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'relationship_type' => 'parent',
            'is_active' => $active,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
