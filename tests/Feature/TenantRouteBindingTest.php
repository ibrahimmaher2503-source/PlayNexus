<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\PlaySession;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantRouteBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_owned_branch_binding_runs_after_tenant_access_and_hides_foreign_scope(): void
    {
        [$tenant, $owner] = $this->tenantOwner();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $foreignBranch = Branch::factory()->create();

        $this->actingAs($owner)
            ->get('/branches/'.$branch->id)
            ->assertOk()
            ->assertJsonPath('tenant_id', $tenant->id);

        $this->get('/branches/'.$foreignBranch->id)->assertNotFound();
    }

    public function test_tenant_owned_binding_fails_closed_without_tenant_context_and_platform_binding_stays_available(): void
    {
        [$tenant] = $this->tenantOwner();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        [$platformAdmin] = $this->platformAdmin();

        $this->post(route('platform.login.store'), [
            'email' => $platformAdmin->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('platform.dashboard'));
        $this->completeMfa($platformAdmin);
        $this->patch(route('platform.tenants.status', $tenant), [
            'status' => 'suspended',
            'expected_status' => 'active',
            'reason_code' => 'access_review',
            'reason' => 'Suspend tenant for route-binding verification.',
        ])
            ->assertRedirect(route('platform.tenants.index'));

        Auth::logout();
        $this->actingAs($platformAdmin)
            ->get('/branches/'.$branch->id)
            ->assertNotFound();
    }

    public function test_session_binding_hides_an_unassigned_branch_before_adjustment_authorization(): void
    {
        [$tenant, $branch, $manager, $session] = $this->sessionFixture('branch_manager');
        DB::table('branch_user')
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branch->id)
            ->where('user_id', $manager->id)
            ->delete();

        $this->actingAs($manager)
            ->postJson(route('sessions.adjustments.store', $session), $this->adjustmentPayload($session))
            ->assertNotFound();
    }

    public function test_session_binding_hides_an_inactive_branch_before_adjustment_authorization(): void
    {
        [$tenant, $branch, $manager, $session] = $this->sessionFixture('branch_manager');
        $branch->update(['is_active' => false]);

        $this->actingAs($manager)
            ->postJson(route('sessions.adjustments.store', $session), $this->adjustmentPayload($session))
            ->assertNotFound();
    }

    public function test_session_binding_keeps_an_accessible_wrong_role_denial_at_403(): void
    {
        [, , $cashier, $session] = $this->sessionFixture('cashier');

        $this->actingAs($cashier)
            ->postJson(route('sessions.adjustments.store', $session), $this->adjustmentPayload($session))
            ->assertForbidden();
    }

    /** @return array{Tenant, User} */
    private function tenantOwner(): array
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);
        DB::table('tenant_owners')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return [$tenant, $owner];
    }

    /** @return array{User} */
    private function platformAdmin(): array
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'email' => 'platform-'.Str::lower(Str::random(8)).'@example.test',
            'password' => Hash::make('password'),
        ]);
        DB::table('platform_admins')->insert([
            'user_id' => $user->id,
            'is_active' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return [$user];
    }

    /** @return array{Tenant, Branch, User, PlaySession} */
    private function sessionFixture(string $role): array
    {
        [$tenant, $owner] = $this->tenantOwner();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($actor, [
            'tenant_id' => $tenant->id,
            'role' => $role,
            'is_active' => true,
        ]);
        $rule = PricingRule::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $owner->id,
            'base_price_minor' => 15000,
            'overtime_price_minor' => 7500,
            'tax_rate_bps' => 0,
        ]);
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $child = Child::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $type = TicketType::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'pricing_rule_id' => $rule->id,
            'code' => 'BIND-'.Str::upper(Str::random(8)),
            'name' => 'Binding test',
            'price_minor' => 15000,
            'currency' => 'EGP',
            'max_uses' => 1,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);
        $ticket = Ticket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'ticket_type_id' => $type->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'service_date' => now()->toDateString(),
            'status' => 'consumed',
            'code_hash' => hash('sha256', Str::random(32)),
            'code_payload_encrypted' => 'payload',
            'display_code' => 'PN-'.Str::upper(Str::random(8)),
            'price_minor' => 15000,
            'currency' => 'EGP',
            'price_snapshot_json' => $this->pricingSnapshot($branch, $rule),
            'issued_at' => now(),
            'valid_from' => now(),
            'valid_until' => now()->addDay(),
            'consumed_at' => now(),
            'uses_count' => 1,
            'max_uses' => 1,
            'idempotency_key' => Str::uuid(),
            'issue_fingerprint' => hash('sha256', Str::random(32)),
            'issued_by_user_id' => $owner->id,
            'lock_version' => 1,
        ]);
        $startedAt = now('UTC')->subHour();
        $session = PlaySession::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'ticket_id' => $ticket->id,
            'pricing_rule_id' => $rule->id,
            'status' => 'active',
            'started_at' => $startedAt,
            'expected_end_at' => $startedAt->addHour(),
            'pricing_snapshot_json' => $this->pricingSnapshot($branch, $rule),
            'created_by_user_id' => $owner->id,
            'lock_version' => 1,
        ]);

        return [$tenant, $branch, $actor, $session];
    }

    private function pricingSnapshot(Branch $branch, PricingRule $rule): array
    {
        return [
            'pricing_rule_id' => $rule->id,
            'base_duration_seconds' => 3600,
            'price_minor' => 15000,
            'grace_period_seconds' => 600,
            'overtime_unit_seconds' => 1800,
            'overtime_price_minor' => 7500,
            'tax_rate_bps' => 0,
            'tax_mode' => 'exclusive',
            'currency' => 'EGP',
            'branch_timezone' => $branch->timezone,
        ];
    }

    /** @return array<string, mixed> */
    private function adjustmentPayload(PlaySession $session): array
    {
        return [
            'expected_lock_version' => $session->lock_version,
            'adjustment_minor' => 100,
            'reason' => 'Binding test adjustment',
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
