<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Route::has('tenant.settings.edit')) {
            Route::middleware(['auth', 'tenant.access'])->group(fn () => require base_path('routes/tenant-settings.php'));
        }
    }

    public function test_owner_can_view_and_update_profile_with_audit(): void
    {
        [$tenant, $owner] = $this->owner();
        $this->actingAs($owner)->get(route('tenant.settings.edit'))->assertOk()->assertSee(__('tenant_settings.title'));

        $this->patch(route('tenant.settings.update'), $this->payload($tenant, ['name' => 'Updated venue', 'legal_name' => 'Updated Venue LLC', 'default_locale' => 'ar']))
            ->assertRedirect(route('tenant.settings.edit'));

        $tenant->refresh();
        $this->assertSame('Updated venue', $tenant->name);
        $this->assertSame('Updated Venue LLC', $tenant->legal_name);
        $this->assertSame('ar', $tenant->default_locale);
        $this->assertSame(2, $tenant->lock_version);
        $audit = DB::table('audit_logs')->where('action', 'tenant.profile.updated')->sole();
        $this->assertSame($owner->id, $audit->actor_user_id);
        $this->assertSame('correction', $audit->reason_code);
    }

    public function test_non_owner_is_forbidden_and_foreign_input_cannot_change_scope(): void
    {
        [$tenant, $owner] = $this->owner();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $foreign = Tenant::factory()->create();
        $payload = $this->payload($tenant, ['tenant_id' => $foreign->id, 'name' => 'Own changed']);
        $this->actingAs($staff)->get(route('tenant.settings.edit'))->assertForbidden();
        $this->patch(route('tenant.settings.update'), $payload)->assertForbidden();
        $this->actingAs($owner)->patch(route('tenant.settings.update'), $payload)->assertRedirect();
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'Own changed']);
        $this->assertDatabaseHas('tenants', ['id' => $foreign->id, 'name' => $foreign->name]);
    }

    public function test_validation_stale_and_noop_behaviour(): void
    {
        [$tenant, $owner] = $this->owner();
        $this->actingAs($owner)->patch(route('tenant.settings.update'), $this->payload($tenant, ['timezone' => 'Invalid/Zone']))->assertSessionHasErrors('timezone');
        $this->patch(route('tenant.settings.update'), $this->payload($tenant, ['currency' => 'USD']))->assertSessionHasErrors('currency');
        $this->patch(route('tenant.settings.update'), $this->payload($tenant))->assertRedirect()->assertSessionHas('status_message');
        $this->assertDatabaseCount('audit_logs', 0);
        DB::table('tenants')->where('id', $tenant->id)->update(['lock_version' => 2]);
        $this->patch(route('tenant.settings.update'), $this->payload($tenant, ['name' => 'Stale change']))->assertStatus(409);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create();
        $tenant->refresh();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$tenant, $owner];
    }

    private function payload(Tenant $tenant, array $overrides = []): array
    {
        return array_merge([
            'name' => $tenant->name, 'legal_name' => $tenant->legal_name, 'default_locale' => $tenant->default_locale,
            'timezone' => $tenant->timezone, 'currency' => 'EGP', 'expected_lock_version' => $tenant->lock_version,
            'reason_code' => 'correction',
        ], $overrides);
    }
}
