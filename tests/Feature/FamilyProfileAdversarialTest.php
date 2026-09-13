<?php

namespace Tests\Feature;

use App\Http\Controllers\FamilyController;
use App\Models\Branch;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class FamilyProfileAdversarialTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_guardian_route_is_not_found_without_disclosure(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $foreignTenant = Tenant::factory()->create();
        $foreignActor = User::factory()->create(['tenant_id' => $foreignTenant->id]);
        $foreignGuardian = Guardian::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'full_name' => 'FOREIGN GUARDIAN SECRET',
            'phone_e164' => '+201099988877',
            'created_by_user_id' => $foreignActor->id,
            'updated_by_user_id' => $foreignActor->id,
        ]);

        $this->actingAs($owner)
            ->getJson(route('families.show', $foreignGuardian))
            ->assertNotFound()
            ->assertDontSee('FOREIGN GUARDIAN SECRET')
            ->assertDontSee('+201099988877');

        $this->assertDatabaseHas('guardians', [
            'id' => $guardian->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_child_from_another_guardian_or_tenant_is_not_mutable(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner, 'Own guardian');
        $otherGuardian = $this->guardian($tenant, $owner, 'Other guardian', '+201000000001');
        $otherChild = $this->child($tenant, $owner, 'Other guardian child');
        $this->link($tenant, $owner, $otherGuardian, $otherChild);

        $foreignTenant = Tenant::factory()->create();
        $foreignActor = User::factory()->create(['tenant_id' => $foreignTenant->id]);
        $foreignGuardian = $this->guardian($foreignTenant, $foreignActor, 'Foreign guardian');
        $foreignChild = $this->child($foreignTenant, $foreignActor, 'Foreign child');
        $this->link($foreignTenant, $foreignActor, $foreignGuardian, $foreignChild);

        foreach ([$otherChild, $foreignChild] as $child) {
            $this->actingAs($owner)
                ->patchJson(route('families.children.update', [$guardian, $child]), [
                    'child_name' => 'MUTATED CHILD MUST NOT SAVE',
                    'date_of_birth' => null,
                    'expected_version' => 1,
                ])
                ->assertNotFound()
                ->assertDontSee('MUTATED CHILD MUST NOT SAVE');
        }

        $this->assertDatabaseHas('children', ['id' => $otherChild->id, 'full_name' => 'Other guardian child']);
        $this->assertDatabaseHas('children', ['id' => $foreignChild->id, 'full_name' => 'Foreign child']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_stale_guardian_child_and_add_child_versions_do_not_write_or_audit(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner, 'Original guardian');
        $child = $this->child($tenant, $owner, 'Original child');
        $this->link($tenant, $owner, $guardian, $child);

        DB::table('guardians')->where('id', $guardian->id)->update(['lock_version' => 2]);
        $this->actingAs($owner)
            ->patchJson(route('families.update', $guardian), $this->guardianPayload(1, [
                'guardian_name' => 'STALE GUARDIAN MUST NOT SAVE',
            ]))
            ->assertStatus(409);

        $this->actingAs($owner)
            ->postJson(route('families.children.store', $guardian), [
                'child_name' => 'STALE ADD MUST NOT SAVE',
                'date_of_birth' => null,
                'relationship_type' => 'parent',
                'expected_version' => 1,
                'child_data_consent' => '1',
                'notice_version' => FamilyController::NOTICE_VERSION,
            ])
            ->assertStatus(409);

        DB::table('children')->where('id', $child->id)->update(['lock_version' => 2]);
        $this->actingAs($owner)
            ->patchJson(route('families.children.update', [$guardian, $child]), [
                'child_name' => 'STALE CHILD MUST NOT SAVE',
                'date_of_birth' => null,
                'expected_version' => 1,
            ])
            ->assertStatus(409);

        $this->assertDatabaseHas('guardians', [
            'id' => $guardian->id,
            'full_name' => 'Original guardian',
            'lock_version' => 2,
        ]);
        $this->assertDatabaseHas('children', [
            'id' => $child->id,
            'full_name' => 'Original child',
            'lock_version' => 2,
        ]);
        $this->assertSame(1, DB::table('children')->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_duplicate_normalized_phone_has_json_and_html_recovery_without_writes(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner, 'Target guardian', '+201001112233');
        $duplicate = $this->guardian($tenant, $owner, 'Existing guardian', '+201002223344');
        $payload = $this->guardianPayload(1, [
            'guardian_name' => 'Changed name must not save',
            'phone' => '0020 100 222 3344',
            'email' => 'changed@example.test',
            'preferred_locale' => 'ar',
        ]);

        $this->actingAs($owner)
            ->patchJson(route('families.update', $guardian), $payload)
            ->assertStatus(409)
            ->assertJsonPath('existing_guardian_id', $duplicate->id)
            ->assertJsonPath('existing_family_url', route('families.show', $duplicate));

        $this->actingAs($owner)
            ->from(route('families.show', $guardian))
            ->patch(route('families.update', $guardian), $payload)
            ->assertRedirect(route('families.show', $guardian))
            ->assertSessionHasErrors('phone')
            ->assertSessionHas('existing_family_url', route('families.show', $duplicate));

        $this->assertDatabaseHas('guardians', [
            'id' => $guardian->id,
            'full_name' => 'Target guardian',
            'phone_e164' => '+201001112233',
            'lock_version' => 1,
        ]);
        $this->assertSame(2, DB::table('guardians')->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseCount('children', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_inactive_actor_assignment_and_branch_are_denied(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner, 'Protected guardian');
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $staff->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        DB::table('users')->where('id', $staff->id)->update(['status' => 'suspended']);
        $this->actingAs($staff->fresh())
            ->getJson(route('families.show', $guardian))
            ->assertUnauthorized();

        DB::table('users')->where('id', $staff->id)->update(['status' => 'active']);
        DB::table('branch_user')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $staff->id)
            ->update(['is_active' => false]);
        $this->actingAs($staff->fresh())
            ->patchJson(route('families.update', $guardian), $this->guardianPayload(1, [
                'guardian_name' => 'Assignment denied must not save',
            ]))
            ->assertForbidden();

        DB::table('branch_user')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $staff->id)
            ->update(['is_active' => true]);
        $branch->update(['is_active' => false]);
        $this->actingAs($staff->fresh())
            ->postJson(route('families.children.store', $guardian), [
                'child_name' => 'Branch denied must not save',
                'date_of_birth' => null,
                'relationship_type' => 'parent',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('guardians', ['id' => $guardian->id, 'full_name' => 'Protected guardian']);
        $this->assertDatabaseCount('children', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_json_contains_ids_and_changed_fields_but_no_raw_pii(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner, 'Original guardian', '+201011111111');
        $child = $this->child($tenant, $owner, 'Original child', '2016-04-03');
        $this->link($tenant, $owner, $guardian, $child);

        $this->actingAs($owner)
            ->patchJson(route('families.update', $guardian), $this->guardianPayload(1, [
                'guardian_name' => 'Updated guardian PII',
                'phone' => '0101 222 3333',
                'email' => 'updated-pii@example.test',
                'preferred_locale' => 'en',
            ]))
            ->assertOk();
        $this->actingAs($owner)
            ->patchJson(route('families.children.update', [$guardian, $child]), [
                'child_name' => 'Updated child PII',
                'date_of_birth' => '2017-05-06',
                'expected_version' => 1,
            ])
            ->assertOk();
        $response = $this->actingAs($owner)
            ->postJson(route('families.children.store', $guardian), [
                'child_name' => 'Added child PII',
                'date_of_birth' => '2018-07-08',
                'relationship_type' => 'mother',
                'expected_version' => 2,
                'child_data_consent' => '1',
                'notice_version' => FamilyController::NOTICE_VERSION,
            ])
            ->assertCreated();

        $addedChildId = (string) $response->json('child.id');
        $audits = DB::table('audit_logs')->orderBy('id')->get()->keyBy('action');
        $guardianAfter = json_decode($audits['family.guardian.updated']->after_json, true, 512, JSON_THROW_ON_ERROR);
        $childAfter = json_decode($audits['family.child.updated']->after_json, true, 512, JSON_THROW_ON_ERROR);
        $addedAfter = json_decode($audits['family.child.added']->after_json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals([
            'guardian_id' => (string) $guardian->id,
            'changed_fields' => ['full_name', 'phone_e164', 'email', 'preferred_locale'],
        ], $guardianAfter);
        $this->assertEquals([
            'child_id' => (string) $child->id,
            'changed_fields' => ['full_name', 'date_of_birth', 'emergency_contact_name', 'emergency_contact_phone_e164'],
        ], $childAfter);
        $this->assertEquals([
            'guardian_id' => (string) $guardian->id,
            'child_id' => $addedChildId,
            'changed_fields' => ['child_name', 'date_of_birth', 'relationship_type'],
        ], $addedAfter);

        $rawAuditJson = $audits->map(fn ($audit): string => (string) $audit->before_json.' '.(string) $audit->after_json)->implode(' ');
        foreach ([
            'Original guardian',
            'Updated guardian PII',
            '+201011111111',
            '+201012223333',
            'updated-pii@example.test',
            'Original child',
            'Updated child PII',
            'Added child PII',
            '2016-04-03',
            '2017-05-06',
            '2018-07-08',
        ] as $pii) {
            $this->assertStringNotContainsString($pii, $rawAuditJson);
        }
        $this->assertSame($tenant->id, $audits['family.guardian.updated']->tenant_id);
        $this->assertSame($owner->id, $audits['family.guardian.updated']->actor_user_id);
    }

    public function test_guardian_update_audit_failure_rolls_back_update_and_version(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner, 'Original guardian', '+201011111111');
        $dispatcher = DB::connection()->getEventDispatcher();
        $this->failOnAuditInsert();

        try {
            $this->withoutExceptionHandling()
                ->actingAs($owner)
                ->patchJson(route('families.update', $guardian), $this->guardianPayload(1, [
                    'guardian_name' => 'Rollback guardian must not save',
                ]));
            $this->fail('The audit failure should have escaped the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseHas('guardians', [
            'id' => $guardian->id,
            'full_name' => 'Original guardian',
            'phone_e164' => '+201011111111',
            'lock_version' => 1,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_add_child_audit_failure_rolls_back_child_and_relationship(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner, 'Existing guardian');
        $dispatcher = DB::connection()->getEventDispatcher();
        $this->failOnAuditInsert();

        try {
            $this->withoutExceptionHandling()
                ->actingAs($owner)
                ->postJson(route('families.children.store', $guardian), [
                    'child_name' => 'Rollback child must not save',
                    'date_of_birth' => null,
                    'relationship_type' => 'parent',
                    'expected_version' => 1,
                    'child_data_consent' => '1',
                    'notice_version' => FamilyController::NOTICE_VERSION,
                ]);
            $this->fail('The audit failure should have escaped the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseHas('guardians', ['id' => $guardian->id, 'lock_version' => 1]);
        $this->assertDatabaseCount('children', 0);
        $this->assertDatabaseCount('guardian_child', 0);
        $this->assertDatabaseCount('audit_logs', 0);
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

    private function guardian(Tenant $tenant, User $actor, string $name, string $phone = '+201000000000'): Guardian
    {
        return Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => $name,
            'phone_e164' => $phone,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
    }

    private function child(Tenant $tenant, User $actor, string $name, ?string $dateOfBirth = null): Child
    {
        return Child::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => $name,
            'date_of_birth' => $dateOfBirth,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
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

    /** @return array<string, mixed> */
    private function guardianPayload(int $expectedVersion, array $overrides = []): array
    {
        return array_merge([
            'guardian_name' => 'Updated guardian',
            'phone' => '0100 111 2233',
            'email' => 'updated@example.test',
            'preferred_locale' => 'en',
            'expected_version' => $expectedVersion,
        ], $overrides);
    }

    private function failOnAuditInsert(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            $sql = strtolower(trim($query->sql));
            if (str_starts_with($sql, 'insert') && str_contains($sql, 'audit_logs')) {
                throw new RuntimeException('audit failure');
            }
        });
    }
}
