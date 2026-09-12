<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class StaffCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_the_bilingual_add_staff_form(): void
    {
        [$tenant, $owner] = $this->owner();

        $this->actingAs($owner)
            ->get(route('staff.create'))
            ->assertOk()
            ->assertViewIs('staff.create')
            ->assertSee(__('staff.add_name'))
            ->assertSee(__('staff.add_email'))
            ->assertSee(__('staff.add_note'))
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="role"', false)
            ->assertDontSee('name="branch_id"', false)
            ->assertDontSee('Invite')
            ->assertDontSee('دعوة')
            ->assertSee($tenant->name);
    }

    public function test_only_an_active_tenant_owner_can_add_staff(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($staff)
            ->get(route('staff.create'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('staff.store'), ['name' => '', 'email' => 'invalid'])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'invalid']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_staff_account_is_tenant_scoped_normalized_unverified_active_and_audited(): void
    {
        [$tenant, $owner] = $this->owner();
        $foreignTenant = Tenant::factory()->create();

        $this->actingAs($owner)
            ->post(route('staff.store'), [
                'name' => '  New Receptionist  ',
                'email' => '  New.Receptionist@Example.TEST ',
                'tenant_id' => $foreignTenant->id,
                'status' => 'active',
                'password' => 'operator-known-password',
                'email_verified_at' => now()->toDateString(),
                'owner' => true,
                'role' => 'branch_manager',
                'branch_id' => 999999,
            ])
            ->assertRedirect(route('staff.index'))
            ->assertSessionHas('success', __('staff.created'));

        $created = User::query()->where('email', 'new.receptionist@example.test')->sole();

        $this->assertSame($tenant->id, $created->tenant_id);
        $this->assertSame('New Receptionist', $created->name);
        $this->assertSame('active', $created->status);
        $this->assertNull($created->email_verified_at);
        $this->assertFalse(Hash::check('operator-known-password', $created->password));
        $this->assertDatabaseMissing('tenant_owners', ['user_id' => $created->id]);
        $this->assertDatabaseMissing('branch_user', ['user_id' => $created->id]);

        $audit = DB::table('audit_logs')->where('subject_id', (string) $created->id)->sole();
        $this->assertSame($tenant->id, $audit->tenant_id);
        $this->assertSame($owner->id, $audit->actor_user_id);
        $this->assertSame('user', $audit->actor_type);
        $this->assertSame('staff.created', $audit->action);
        $this->assertSame('user', $audit->subject_type);
        $this->assertSame('success', $audit->outcome);
        $this->assertSame('staffing_change', $audit->reason_code);
        $this->assertNull($audit->before_json);
        $this->assertSame(['status' => 'active'], json_decode($audit->after_json, true));
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $audit->request_id,
        );
    }

    public function test_duplicate_email_is_a_generic_field_error_across_tenants(): void
    {
        [$tenant, $owner] = $this->owner();
        $foreignTenant = Tenant::factory()->create(['name' => 'Foreign Secret Tenant']);
        $existing = User::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'email' => 'already-used@example.test',
        ]);

        $this->actingAs($owner)
            ->postJson(route('staff.store'), [
                'name' => 'Duplicate',
                'email' => ' ALREADY-USED@EXAMPLE.TEST ',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => __('staff.validation.email_taken'),
            ])
            ->assertDontSee($foreignTenant->name)
            ->assertDontSee($existing->email);

        $this->assertDatabaseMissing('users', ['email' => 'duplicate@example.test']);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertTrue($tenant->is_active);
    }

    public function test_audit_failure_rolls_back_the_created_user(): void
    {
        [$tenant, $owner] = $this->owner();

        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            $sql = strtolower(trim($query->sql));
            if (str_starts_with($sql, 'insert') && str_contains($sql, 'audit_logs')) {
                throw new RuntimeException('staff audit failure');
            }
        });

        try {
            $this->actingAs($owner)
                ->post(route('staff.store'), [
                    'name' => 'Rollback Invite',
                    'email' => 'rollback@example.test',
                ])
                ->assertServerError();
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseMissing('users', [
            'tenant_id' => $tenant->id,
            'email' => 'rollback@example.test',
        ]);
    }

    public function test_created_user_cannot_log_in_with_submitted_password(): void
    {
        [, $owner] = $this->owner();

        $this->actingAs($owner)
            ->post(route('staff.store'), [
                'name' => 'Pending Login',
                'email' => 'pending-login@example.test',
                'password' => 'known-to-operator',
            ])
            ->assertRedirect(route('staff.index'));

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'pending-login@example.test',
            'password' => 'known-to-operator',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
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
}
