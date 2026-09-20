<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FamilyRetentionHold;
use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use App\Support\FamilyRetention;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FamilyPrivacyRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_review_three_year_eligibility_without_mutating_family_data(): void
    {
        [$tenant, $owner] = $this->owner();
        $eligible = $this->guardian($tenant, $owner, 'Eligible Family', now('UTC')->subYears(4));
        $notDue = $this->guardian($tenant, $owner, 'Recent Family', now('UTC')->subYear());
        $dryRun = FamilyRetention::dryRun($tenant)->keyBy(fn (array $row): int => $row['guardian']->id);
        $this->assertTrue($dryRun[$eligible->id]['eligible']);
        $this->assertFalse($dryRun[$notDue->id]['eligible']);

        $this->actingAs($owner)
            ->get(route('families.privacy.index'))
            ->assertOk()
            ->assertSee('Eligible Family')
            ->assertSee('Recent Family')
            ->assertSee(__('privacy.eligible'))
            ->assertSee(__('privacy.not_due'))
            ->assertSee(__('privacy.destructive_disabled'));

        $this->assertDatabaseCount('guardians', 2);
        $this->assertDatabaseHas('guardians', ['id' => $eligible->id, 'status' => 'active']);
        $this->assertDatabaseHas('guardians', ['id' => $notDue->id, 'status' => 'active']);
    }

    public function test_hold_create_and_release_are_tenant_scoped_idempotent_and_audited_without_reason_leakage(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = $this->guardian($tenant, $owner, 'Held Family', now('UTC')->subYears(4));

        $this->actingAs($owner)->post(route('families.privacy.holds.store'), [
            'guardian_id' => $guardian->id,
            'category' => 'legal',
            'reason' => 'Preserve while the legal request remains open.',
        ])->assertRedirect(route('families.privacy.index'));
        $this->actingAs($owner)->post(route('families.privacy.holds.store'), [
            'guardian_id' => $guardian->id,
            'category' => 'legal',
            'reason' => 'Repeated request must not create another hold.',
        ])->assertRedirect(route('families.privacy.index'));

        $this->assertDatabaseCount('family_retention_holds', 1);
        $hold = FamilyRetentionHold::query()->sole();
        $placedAudit = DB::table('audit_logs')->where('action', 'family.retention_hold.placed')->sole();
        $this->assertStringContainsString((string) $guardian->id, $placedAudit->after_json);
        $this->assertStringNotContainsString('Preserve while', $placedAudit->after_json);

        $this->actingAs($owner)->patch(route('families.privacy.holds.release', $hold), [
            'release_reason' => 'The legal preservation requirement has ended.',
        ])->assertRedirect(route('families.privacy.index'));
        $this->actingAs($owner)->patch(route('families.privacy.holds.release', $hold), [
            'release_reason' => 'A repeated release remains safely idempotent.',
        ])->assertRedirect(route('families.privacy.index'));

        $this->assertDatabaseHas('family_retention_holds', ['id' => $hold->id, 'status' => 'released', 'released_by_user_id' => $owner->id]);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'family.retention_hold.released')->count());
    }

    public function test_non_owner_and_foreign_guardian_are_denied_without_disclosure(): void
    {
        [$tenant, $owner] = $this->owner();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $branch->users()->attach($staff, ['tenant_id' => $tenant->id, 'role' => 'branch_manager', 'is_active' => true]);
        [$foreignTenant, $foreignOwner] = $this->owner();
        $foreignGuardian = $this->guardian($foreignTenant, $foreignOwner, 'Foreign Family', now('UTC')->subYears(4));

        $this->actingAs($staff)->get(route('families.privacy.index'))->assertForbidden();
        $this->actingAs($owner)->post(route('families.privacy.holds.store'), [
            'guardian_id' => $foreignGuardian->id,
            'category' => 'legal',
            'reason' => 'Attempted cross-tenant preservation request.',
        ])->assertNotFound();

        $this->assertDatabaseCount('family_retention_holds', 0);
    }

    public function test_database_rejects_cross_tenant_hold_ownership(): void
    {
        [$tenant, $owner] = $this->owner();
        [$foreignTenant, $foreignOwner] = $this->owner();
        $foreignGuardian = $this->guardian($foreignTenant, $foreignOwner, 'Foreign Family', now('UTC')->subYears(4));

        $this->expectException(QueryException::class);
        FamilyRetentionHold::query()->create([
            'tenant_id' => $tenant->id,
            'guardian_id' => $foreignGuardian->id,
            'category' => 'legal',
            'reason' => 'Cross tenant rows must fail at the database boundary.',
            'status' => 'active',
            'placed_by_user_id' => $owner->id,
            'placed_at' => now('UTC'),
            'request_id' => fake()->uuid(),
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

    private function guardian(Tenant $tenant, User $actor, string $name, mixed $updatedAt): Guardian
    {
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => $name,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
        DB::table('guardians')->where('id', $guardian->id)->update(['created_at' => $updatedAt, 'updated_at' => $updatedAt]);

        return $guardian->fresh();
    }
}
