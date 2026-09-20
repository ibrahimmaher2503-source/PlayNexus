<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_edits_identity_without_mass_assigning_role_tenant_status_or_password(): void
    {
        [$tenant, $owner, $target] = $this->fixture();
        $password = $target->password;
        $version = (int) $target->auth_version;
        DB::table('password_reset_tokens')->insert(['email' => $target->email, 'token' => 'hashed-token', 'created_at' => now()]);
        $oldEmail = $target->email;
        $this->actingAs($owner)->get(route('staff.edit', $target))->assertOk();
        $response = $this->patch(route('staff.identity', $target), $this->payload($target) + [
            'tenant_id' => Tenant::factory()->create()->id, 'role' => 'owner', 'status' => 'disabled', 'password' => 'injected-password',
        ])->assertRedirect(route('staff.index'));
        $target->refresh();
        $this->assertSame($tenant->id, $target->tenant_id);
        $this->assertSame('active', $target->status);
        $this->assertSame($password, $target->password);
        $this->assertGreaterThan($version, (int) $target->auth_version);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $oldEmail]);
        $audit = DB::table('audit_logs')->where('action', 'staff.identity.updated')->sole();
        $this->assertStringNotContainsString($oldEmail, $audit->before_json);
        $this->assertSame($owner->id, $audit->actor_user_id);
        $this->assertSame($response->headers->get('X-Request-ID'), $audit->request_id);
        $this->actingAs($target)->withSession(['auth_version' => $version])->getJson(route('dashboard'))->assertUnauthorized();
    }

    public function test_invalid_duplicate_stale_and_foreign_requests_preserve_identity(): void
    {
        [, $owner, $target] = $this->fixture();
        $this->actingAs($owner)->patch(route('staff.identity', $target), $this->payload($target, ['email' => $owner->email]))->assertSessionHasErrors('email');
        $this->patch(route('staff.identity', $target), $this->payload($target, ['name' => '']))->assertSessionHasErrors('name');
        $this->patch(route('staff.identity', $target), $this->payload($target, ['expected_name' => 'stale']))->assertStatus(409);
        $foreign = User::factory()->create();
        $this->get(route('staff.edit', $foreign))->assertNotFound();
        $this->patch(route('staff.identity', $foreign), $this->payload($foreign))->assertNotFound();
        $this->assertSame($target->email, $target->fresh()->email);
    }

    public function test_lower_roles_cannot_edit_identity_or_owner_account(): void
    {
        [, $owner, $target] = $this->fixture();
        $this->actingAs($target)->get(route('staff.edit', $target))->assertForbidden();
        $this->patch(route('staff.identity', $target), $this->payload($target))->assertForbidden();
        $this->actingAs($owner)->get(route('staff.edit', $owner))->assertForbidden();
    }

    private function fixture(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $target = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$tenant, $owner, $target];
    }

    private function payload(User $target, array $overrides = []): array
    {
        return array_replace(['name' => 'Edited staff', 'email' => 'edited-staff@example.test', 'expected_name' => $target->name, 'expected_email' => $target->email], $overrides);
    }
}
