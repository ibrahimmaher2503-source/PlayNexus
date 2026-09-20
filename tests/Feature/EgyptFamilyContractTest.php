<?php

namespace Tests\Feature;

use App\Http\Controllers\FamilyController;
use App\Models\Branch;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EgyptFamilyContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_registration_requires_explicit_versioned_consent(): void
    {
        [$tenant, $owner] = $this->owner();

        $this->actingAs($owner)
            ->postJson(route('families.store'), $this->payload([
                'child_data_consent' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('child_data_consent');

        $this->assertDatabaseCount('guardians', 0);
        $this->assertDatabaseCount('children', 0);
        $this->assertDatabaseCount('family_consent_events', 0);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }

    public function test_registration_records_emergency_consent_and_encrypted_safety_data_atomically(): void
    {
        [$tenant, $owner] = $this->owner();

        $response = $this->actingAs($owner)
            ->postJson(route('families.store'), $this->payload([
                'safety_notes' => 'Severe peanut allergy',
                'marketing_consent' => true,
            ]))
            ->assertCreated();

        $child = Child::query()->findOrFail($response->json('child_id'));
        $this->assertSame('Parent One', $child->emergency_contact_name);
        $this->assertSame('+201001112233', $child->emergency_contact_phone_e164);
        $this->assertSame('Severe peanut allergy', $child->safety_notes_encrypted);
        $this->assertNotSame('Severe peanut allergy', DB::table('children')->where('id', $child->id)->value('safety_notes_encrypted'));
        $this->assertDatabaseHas('guardian_child', [
            'tenant_id' => $tenant->id,
            'child_id' => $child->id,
            'relationship_type' => 'legal_guardian',
            'can_consent' => 1,
            'can_check_out' => 1,
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('family_consent_events', [
            'tenant_id' => $tenant->id,
            'child_id' => $child->id,
            'consent_type' => 'child_data',
            'status' => 'granted',
            'notice_version' => FamilyController::NOTICE_VERSION,
        ]);
        $this->assertDatabaseHas('family_consent_events', [
            'tenant_id' => $tenant->id,
            'child_id' => $child->id,
            'consent_type' => 'marketing',
            'status' => 'granted',
        ]);

        $this->actingAs($owner)
            ->patchJson(route('families.children.consent.withdraw', [Guardian::query()->firstOrFail(), $child]), [
                'consent_type' => 'marketing',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('changed', true);
        $this->actingAs($owner)
            ->patchJson(route('families.children.consent.withdraw', [Guardian::query()->firstOrFail(), $child]), [
                'consent_type' => 'marketing',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('changed', false);
        $this->assertDatabaseHas('family_consent_events', [
            'child_id' => $child->id,
            'consent_type' => 'marketing',
            'status' => 'withdrawn',
        ]);
        $this->assertDatabaseHas('children', ['id' => $child->id, 'status' => 'active', 'lock_version' => 1]);
        $this->assertSame(2, DB::table('family_consent_events')->where('child_id', $child->id)->where('consent_type', 'marketing')->count());
    }

    public function test_cashier_cannot_write_safety_notes_or_manage_consent_and_relationships(): void
    {
        [$tenant, $owner] = $this->owner();
        $cashier = $this->staff($tenant, 'cashier');

        $this->actingAs($cashier)
            ->postJson(route('families.store'), $this->payload(['safety_notes' => 'Hidden medical detail']))
            ->assertForbidden();

        [$guardian, $child] = $this->registeredFamily($owner);
        $this->actingAs($cashier)
            ->patchJson(route('families.children.consent.withdraw', [$guardian, $child]), [
                'consent_type' => 'child_data',
                'expected_version' => 1,
            ])
            ->assertForbidden();
        $this->actingAs($cashier)
            ->postJson(route('families.relationships.store', [$guardian, $child]), [
                'guardian_phone' => '01009998877',
                'relationship_type' => 'authorized_pickup',
                'verification_method' => 'registered_phone_last_four',
                'verification_value' => '8877',
                'expected_version' => 1,
            ])
            ->assertForbidden();
    }

    public function test_child_data_withdrawal_is_append_only_and_restricts_the_child(): void
    {
        [, $owner] = $this->owner();
        [$guardian, $child] = $this->registeredFamily($owner);

        $this->actingAs($owner)
            ->patchJson(route('families.children.consent.withdraw', [$guardian, $child]), [
                'consent_type' => 'child_data',
                'expected_version' => 1,
            ])
            ->assertOk();

        $this->assertDatabaseHas('children', ['id' => $child->id, 'status' => 'restricted', 'lock_version' => 2]);
        $this->assertDatabaseHas('family_consent_events', ['child_id' => $child->id, 'consent_type' => 'child_data', 'status' => 'granted']);
        $this->assertDatabaseHas('family_consent_events', ['child_id' => $child->id, 'consent_type' => 'child_data', 'status' => 'withdrawn']);
        $this->assertSame(2, DB::table('family_consent_events')->where('child_id', $child->id)->where('consent_type', 'child_data')->count());
    }

    public function test_verified_legal_guardian_can_record_renewed_consent_without_overwriting_history(): void
    {
        [, $owner] = $this->owner();
        [$guardian, $child] = $this->registeredFamily($owner);

        $this->actingAs($owner)->patchJson(route('families.children.consent.withdraw', [$guardian, $child]), [
            'consent_type' => 'child_data',
            'expected_version' => 1,
        ])->assertOk();

        $this->actingAs($owner)
            ->get(route('families.show', $guardian))
            ->assertOk()
            ->assertSee(__('families.consent_status_withdrawn'))
            ->assertSee(__('families.restore_child_data_consent'));

        $this->actingAs($owner)->postJson(route('families.children.consent.grant', [$guardian, $child]), [
            'expected_version' => 2,
            'notice_version' => FamilyController::NOTICE_VERSION,
            'child_data_consent' => true,
        ])->assertOk()->assertJsonPath('changed', true);

        $this->assertDatabaseHas('children', ['id' => $child->id, 'status' => 'active', 'lock_version' => 3]);
        $events = DB::table('family_consent_events')
            ->where('child_id', $child->id)
            ->where('consent_type', 'child_data')
            ->orderBy('id')
            ->pluck('status')
            ->all();
        $this->assertSame(['granted', 'withdrawn', 'granted'], $events);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $guardian->tenant_id,
            'action' => 'family.consent.granted',
            'subject_type' => 'child',
            'subject_id' => (string) $child->id,
        ]);
    }

    public function test_non_consent_relationship_cannot_renew_child_data_consent(): void
    {
        [$tenant, $owner] = $this->owner();
        [$guardian, $child] = $this->registeredFamily($owner);
        $pickup = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'phone_e164' => '+201008887766',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        DB::table('guardian_child')->insert([
            'tenant_id' => $tenant->id,
            'guardian_id' => $pickup->id,
            'child_id' => $child->id,
            'relationship_type' => 'authorized_pickup',
            'can_consent' => false,
            'can_check_out' => true,
            'is_primary' => false,
            'verification_method' => 'registered_phone_last_four',
            'verified_at' => now(),
            'verified_by_user_id' => $owner->id,
            'is_active' => true,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($owner)->patchJson(route('families.children.consent.withdraw', [$guardian, $child]), [
            'consent_type' => 'child_data',
            'expected_version' => 1,
        ])->assertOk();

        $this->actingAs($owner)->postJson(route('families.children.consent.grant', [$pickup, $child]), [
            'expected_version' => 2,
            'notice_version' => FamilyController::NOTICE_VERSION,
            'child_data_consent' => true,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('children', ['id' => $child->id, 'status' => 'restricted', 'lock_version' => 2]);
        $this->assertSame(2, DB::table('family_consent_events')->where('child_id', $child->id)->where('consent_type', 'child_data')->count());
    }

    public function test_relationship_lifecycle_blocks_the_last_legal_guardian_and_allows_verified_addition(): void
    {
        [$tenant, $owner] = $this->owner();
        [$guardian, $child] = $this->registeredFamily($owner);

        $this->actingAs($owner)
            ->deleteJson(route('families.relationships.revoke', [$guardian, $child, $guardian]), ['expected_version' => 1])
            ->assertUnprocessable();

        $second = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'phone_e164' => '+201009998877',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $this->actingAs($owner)
            ->postJson(route('families.relationships.store', [$guardian, $child]), [
                'guardian_phone' => $second->phone_e164,
                'relationship_type' => 'mother',
                'can_check_out' => true,
                'verification_method' => 'registered_phone_last_four',
                'verification_value' => '8877',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('child_version', 2);
        $this->assertDatabaseHas('guardian_child', [
            'tenant_id' => $tenant->id,
            'guardian_id' => $second->id,
            'child_id' => $child->id,
            'can_consent' => 1,
            'can_check_out' => 1,
            'is_active' => 1,
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('families.relationships.revoke', [$guardian, $child, $guardian]), ['expected_version' => 2])
            ->assertOk()
            ->assertJsonPath('child_version', 3);

        $this->actingAs($owner)
            ->deleteJson(route('families.relationships.revoke', [$second, $child, $second]), ['expected_version' => 3])
            ->assertUnprocessable();
    }

    public function test_authorized_pickup_never_receives_consent_authority(): void
    {
        [$tenant, $owner] = $this->owner();
        [$guardian, $child] = $this->registeredFamily($owner);
        $pickup = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'phone_e164' => '+201008887766',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->postJson(route('families.relationships.store', [$guardian, $child]), [
                'guardian_phone' => $pickup->phone_e164,
                'relationship_type' => 'authorized_pickup',
                'can_check_out' => true,
                'verification_method' => 'registered_phone_last_four',
                'verification_value' => '0000',
                'expected_version' => 1,
            ])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('guardian_child', ['guardian_id' => $pickup->id, 'child_id' => $child->id]);

        $this->actingAs($owner)
            ->postJson(route('families.relationships.store', [$guardian, $child]), [
                'guardian_phone' => $pickup->phone_e164,
                'relationship_type' => 'authorized_pickup',
                'can_check_out' => true,
                'verification_method' => 'registered_phone_last_four',
                'verification_value' => '7766',
                'expected_version' => 1,
            ])
            ->assertOk();

        $this->assertDatabaseHas('guardian_child', [
            'guardian_id' => $pickup->id,
            'child_id' => $child->id,
            'can_consent' => 0,
            'can_check_out' => 1,
        ]);
    }

    public function test_database_rejects_duplicate_normalized_phone_inside_one_tenant(): void
    {
        [$tenant, $owner] = $this->owner();
        Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'phone_e164' => '+201001112233',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);

        $this->expectException(QueryException::class);
        Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'phone_e164' => '+201001112233',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$tenant, $owner];
    }

    private function staff(Tenant $tenant, string $role): User
    {
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $branch->users()->attach($staff, ['tenant_id' => $tenant->id, 'role' => $role, 'is_active' => true]);

        return $staff;
    }

    /** @return array{Guardian, Child} */
    private function registeredFamily(User $actor): array
    {
        $response = $this->actingAs($actor)->postJson(route('families.store'), $this->payload())->assertCreated();

        return [Guardian::query()->findOrFail($response->json('guardian_id')), Child::query()->findOrFail($response->json('child_id'))];
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'guardian_name' => 'Parent One',
            'phone' => '01001112233',
            'email' => null,
            'preferred_locale' => 'ar',
            'child_name' => 'Child One',
            'date_of_birth' => null,
            'relationship_type' => 'legal_guardian',
            'child_data_consent' => true,
            'notice_version' => FamilyController::NOTICE_VERSION,
        ], $overrides);
    }
}
