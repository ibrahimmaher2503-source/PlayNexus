<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class BranchSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('branches.settings')) {
            Route::middleware(['auth', 'tenant.access'])->group(function (): void {
                require base_path('routes/branch-settings.php');
            });
        }
    }

    public function test_migration_adds_settings_columns_and_opening_hours_table(): void
    {
        $this->assertTrue(Schema::hasColumns('branches', [
            'code',
            'address_text',
            'timezone',
            'capacity',
            'currency',
            'tax_rate_bps',
            'tax_mode',
            'receipt_prefix',
            'payment_methods',
            'lock_version',
        ]));
        $this->assertTrue(Schema::hasTable('branch_opening_hours'));
        $this->assertTrue(Schema::hasColumns('branch_opening_hours', [
            'tenant_id',
            'branch_id',
            'weekday',
            'opens_at',
            'closes_at',
            'is_closed',
        ]));

        [$tenant] = $this->owner();
        $branch = $this->branch($tenant);
        $this->assertSame(1, (int) DB::table('branches')->where('id', $branch->id)->value('capacity'));
        $this->assertSame('Africa/Cairo', DB::table('branches')->where('id', $branch->id)->value('timezone'));
        $this->assertSame('EGP', DB::table('branches')->where('id', $branch->id)->value('currency'));
        $this->assertSame('exclusive', DB::table('branches')->where('id', $branch->id)->value('tax_mode'));
        $this->assertSame('PN', DB::table('branches')->where('id', $branch->id)->value('receipt_prefix'));
        $this->assertSame(1, (int) DB::table('branches')->where('id', $branch->id)->value('lock_version'));
    }

    public function test_owner_can_save_settings_and_reload_all_seven_days(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Configured branch');
        $payload = $this->settingsPayload($branch);

        $this->actingAs($owner)
            ->patchJson(route('branches.settings.update', $branch), $payload)
            ->assertOk()
            ->assertJsonPath('changed', true)
            ->assertJsonPath('lock_version', 2);

        $stored = DB::table('branches')->where('id', $branch->id)->first();
        $this->assertSame('BR-CONFIGURED', $stored->code);
        $this->assertSame('Configured branch', $stored->name);
        $this->assertSame('6 October City', $stored->address_text);
        $this->assertSame('Africa/Cairo', $stored->timezone);
        $this->assertSame(12, (int) $stored->capacity);
        $this->assertSame('EGP', $stored->currency);
        $this->assertSame(1450, (int) $stored->tax_rate_bps);
        $this->assertSame('inclusive', $stored->tax_mode);
        $this->assertSame('PLAY', $stored->receipt_prefix);
        $this->assertSame(['cash'], json_decode($stored->payment_methods, true));
        $this->assertSame(2, (int) $stored->lock_version);

        $hours = DB::table('branch_opening_hours')
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branch->id)
            ->orderBy('weekday')
            ->get();
        $this->assertCount(7, $hours);
        $this->assertSame('09:00', substr((string) $hours[0]->opens_at, 0, 5));
        $this->assertSame('18:00', substr((string) $hours[0]->closes_at, 0, 5));
        $this->assertSame(0, (int) $hours[0]->is_closed);
        $this->assertNull($hours[1]->opens_at);
        $this->assertSame(1, (int) $hours[1]->is_closed);

        $audit = DB::table('audit_logs')->where('action', 'branch.settings.updated')->sole();
        $this->assertSame($tenant->id, $audit->tenant_id);
        $this->assertSame($branch->id, $audit->branch_id);
        $this->assertSame($owner->id, $audit->actor_user_id);
        $this->assertSame('branch', $audit->subject_type);
        $this->assertSame((string) $branch->id, $audit->subject_id);
        $this->assertSame('success', $audit->outcome);
        $this->assertSame('setup_change', $audit->reason_code);
        $this->assertSame('BR-CONFIGURED', json_decode($audit->after_json, true)['code']);
        $this->assertNotEmpty($audit->request_id);

        $this->get(route('branches.settings', $branch))
            ->assertOk()
            ->assertViewIs('branches.settings')
            ->assertSee('value="BR-CONFIGURED"', false)
            ->assertSee('value="12"', false)
            ->assertSee('value="09:00"', false)
            ->assertSee('value="18:00"', false)
            ->assertSee(__('branch_settings.inclusive'));
    }

    public function test_unassigned_staff_cannot_read_or_update_same_tenant_settings(): void
    {
        [$tenant, $owner] = $this->owner();
        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch = $this->branch($tenant);
        $payload = $this->settingsPayload($branch);

        $this->actingAs($staff)
            ->get(route('branches.settings', $branch))
            ->assertNotFound();
        $this->patchJson(route('branches.settings.update', $branch), $payload)
            ->assertNotFound();
        $this->assertSame(0, DB::table('audit_logs')->where('action', '!=', 'security.request_denied')->count());

        $foreignBranch = $this->branch(Tenant::factory()->create(), 'Foreign settings branch');
        $this->actingAs($owner)
            ->get(route('branches.settings', $foreignBranch))
            ->assertNotFound();
        $this->patchJson(route('branches.settings.update', $foreignBranch), $payload)
            ->assertNotFound();
    }

    public function test_assigned_branch_manager_can_update_only_the_managed_branch_and_reception_is_denied(): void
    {
        [$tenant] = $this->owner();
        $managed = $this->branch($tenant, 'Managed settings');
        $unassigned = $this->branch($tenant, 'Unassigned settings');
        $manager = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $reception = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        foreach ([[$manager, 'branch_manager'], [$reception, 'reception_staff']] as [$user, $role]) {
            DB::table('branch_user')->insert([
                'tenant_id' => $tenant->id,
                'branch_id' => $managed->id,
                'user_id' => $user->id,
                'role' => $role,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($manager)
            ->get(route('branches.settings', $managed))
            ->assertOk();
        $this->patchJson(route('branches.settings.update', $managed), $this->settingsPayload($managed))
            ->assertOk()
            ->assertJsonPath('changed', true);
        $this->get(route('branches.settings', $unassigned))->assertNotFound();

        $this->actingAs($reception)
            ->get(route('branches.settings', $managed))
            ->assertForbidden();
        $this->patchJson(route('branches.settings.update', $managed), $this->settingsPayload($managed))
            ->assertForbidden();

        DB::table('branch_user')->where('user_id', $reception->id)->update(['role' => 'cashier']);
        $this->get(route('branches.settings', $managed))->assertForbidden();
        DB::table('branch_user')->where('user_id', $manager->id)->update(['is_active' => false]);
        $this->actingAs($manager)->get(route('branches.settings', $managed))->assertNotFound();
    }

    public function test_owner_can_read_and_update_an_inactive_own_branch(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant, 'Inactive branch', false);
        $payload = $this->settingsPayload($branch);

        $this->actingAs($owner)
            ->get(route('branches.settings', $branch))
            ->assertOk()
            ->assertSee(__('branch_settings.inactive'));
        $this->patchJson(route('branches.settings.update', $branch), $payload)
            ->assertOk()
            ->assertJsonPath('changed', true);
        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => 0, 'lock_version' => 2]);
    }

    public function test_settings_validation_rejects_invalid_identity_financial_values_and_hours(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $base = $this->settingsPayload($branch);
        $this->actingAs($owner);

        $invalid = $base;
        $invalid['code'] = '<script>';
        $invalid['timezone'] = 'Not/AZone';
        $invalid['capacity'] = 0;
        $invalid['currency'] = 'USD';
        $invalid['tax_rate_bps'] = 10001;
        $invalid['receipt_prefix'] = '<bad>';
        $invalid['payment_methods'] = ['cash', 'card'];
        $invalid['opening_hours'][0]['is_closed'] = false;
        $invalid['opening_hours'][0]['opens_at'] = null;
        $invalid['opening_hours'][0]['closes_at'] = '08:00';
        $invalid['opening_hours'][6]['weekday'] = 6;

        $this->patchJson(route('branches.settings.update', $branch), $invalid)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
                'timezone',
                'capacity',
                'currency',
                'tax_rate_bps',
                'receipt_prefix',
                'payment_methods',
                'opening_hours.0.opens_at',
                'opening_hours.6.weekday',
            ]);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame(1, (int) DB::table('branches')->where('id', $branch->id)->value('lock_version'));
    }

    public function test_opening_hours_require_exactly_seven_unique_days_and_valid_ranges(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $base = $this->settingsPayload($branch);
        $this->actingAs($owner);

        $missingDay = $base;
        array_pop($missingDay['opening_hours']);
        $this->patchJson(route('branches.settings.update', $branch), $missingDay)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_hours']);

        $duplicateDay = $base;
        $duplicateDay['opening_hours'][6]['weekday'] = 6;
        $this->patchJson(route('branches.settings.update', $branch), $duplicateDay)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_hours.6.weekday']);

        $closedWithTimes = $base;
        $closedWithTimes['opening_hours'][1]['opens_at'] = '09:00';
        $this->patchJson(route('branches.settings.update', $branch), $closedWithTimes)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_hours.1.opens_at']);

        $reversed = $base;
        $reversed['opening_hours'][0]['closes_at'] = '08:00';
        $this->patchJson(route('branches.settings.update', $branch), $reversed)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_hours.0.closes_at']);
    }

    public function test_currency_change_is_rejected_after_financial_records_are_posted_and_history_is_unchanged(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $now = now('UTC');
        $orderId = DB::table('orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'status' => 'paid',
            'subtotal_minor' => 10_000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 10_000,
            'paid_minor' => 10_000,
            'refunded_minor' => 0,
            'currency' => 'EGP',
            'opened_by_user_id' => $owner->id,
            'paid_by_user_id' => $owner->id,
            'paid_at' => $now,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('payments')->insert([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'order_id' => $orderId,
            'method' => 'cash',
            'status' => 'posted',
            'amount_minor' => 10_000,
            'currency' => 'EGP',
            'posted_by_user_id' => $owner->id,
            'posted_at' => $now,
            'idempotency_key' => 'branch-currency-history',
            'request_fingerprint' => hash('sha256', 'branch-currency-history'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $payload = $this->settingsPayload($branch);
        $payload['currency'] = 'USD';

        $this->actingAs($owner)
            ->patchJson(route('branches.settings.update', $branch), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency']);

        $this->assertSame('EGP', $branch->fresh()->currency);
        $this->assertSame('EGP', DB::table('orders')->where('id', $orderId)->value('currency'));
        $this->assertSame('EGP', DB::table('payments')->where('order_id', $orderId)->value('currency'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_stale_version_returns_conflict_and_noop_does_not_bump_or_audit(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $payload = $this->settingsPayload($branch);
        $this->actingAs($owner);

        $this->patchJson(route('branches.settings.update', $branch), $payload)->assertOk();
        $payload['expected_lock_version'] = 2;
        $this->patchJson(route('branches.settings.update', $branch), $payload)
            ->assertOk()
            ->assertJsonPath('changed', false)
            ->assertJsonPath('lock_version', 2);
        $this->assertSame(2, (int) DB::table('branches')->where('id', $branch->id)->value('lock_version'));
        $this->assertDatabaseCount('audit_logs', 1);

        $stale = $payload;
        $stale['expected_lock_version'] = 1;
        $stale['capacity'] = 30;
        $this->patchJson(route('branches.settings.update', $branch), $stale)
            ->assertStatus(409)
            ->assertSee(__('branch_settings.conflict'));
        $this->assertSame(12, (int) DB::table('branches')->where('id', $branch->id)->value('capacity'));
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_audit_failure_rolls_back_branch_and_hours_and_version(): void
    {
        [$tenant, $owner] = $this->owner();
        $branch = $this->branch($tenant);
        $payload = $this->settingsPayload($branch);
        $dispatcher = DB::connection()->getEventDispatcher();
        DB::listen(function (QueryExecuted $query): void {
            $sql = strtolower(trim($query->sql));
            if (str_starts_with($sql, 'insert') && str_contains($sql, 'audit_logs')) {
                throw new RuntimeException('audit failure');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($owner)
                ->patchJson(route('branches.settings.update', $branch), $payload);
            $this->fail('The audit failure should have escaped the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failure', $exception->getMessage());
        } finally {
            DB::connection()->setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'lock_version' => 1,
            'capacity' => 1,
        ]);
        $this->assertDatabaseCount('branch_opening_hours', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $owner];
    }

    private function branch(Tenant $tenant, string $name = 'Settings branch', bool $active = true): Branch
    {
        return Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'is_active' => $active,
        ]);
    }

    /** @return array<string, mixed> */
    private function settingsPayload(Branch $branch): array
    {
        $openingHours = [];
        foreach (range(1, 7) as $weekday) {
            $openingHours[] = [
                'weekday' => $weekday,
                'is_closed' => true,
                'opens_at' => null,
                'closes_at' => null,
            ];
        }
        $openingHours[0] = [
            'weekday' => 1,
            'is_closed' => false,
            'opens_at' => '09:00',
            'closes_at' => '18:00',
        ];

        return [
            'code' => 'BR-CONFIGURED',
            'name' => 'Configured branch',
            'address_text' => '6 October City',
            'timezone' => 'Africa/Cairo',
            'capacity' => 12,
            'currency' => 'EGP',
            'tax_rate_bps' => 1450,
            'tax_mode' => 'inclusive',
            'receipt_prefix' => 'PLAY',
            'payment_methods' => ['cash'],
            'expected_lock_version' => (int) (DB::table('branches')->where('id', $branch->id)->value('lock_version') ?? 1),
            'opening_hours' => $openingHours,
        ];
    }
}
