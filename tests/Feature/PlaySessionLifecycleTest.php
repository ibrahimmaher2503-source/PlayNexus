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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PlaySessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_extend_uses_snapshot_price_updates_end_and_appends_evidence(): void
    {
        [$tenant, $owner, , , $session] = $this->fixture();
        $key = (string) Str::uuid();

        $this->actingAs($owner)->postJson($this->url($session, 'extend'), [
            'expected_lock_version' => 1,
            'extension_units' => 1,
            'idempotency_key' => $key,
        ])->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('lock_version', 2)
            ->assertJsonPath('extension_units_added', 1)
            ->assertJsonPath('extension_amount_minor_added', 7500)
            ->assertJsonPath('extension_seconds_added', 1800);

        $updated = $session->fresh();
        $this->assertSame(1, (int) DB::table('play_session_adjustments')->where('session_id', $session->id)->sum('extension_units'));
        $this->assertSame($session->started_at->addSeconds(5400)->timestamp, $updated->expected_end_at->timestamp);
        $this->assertDatabaseHas('play_session_events', ['session_id' => $session->id, 'event_type' => 'extended']);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'session.extended']);
    }

    public function test_html_lifecycle_posts_redirect_to_the_scoped_board(): void
    {
        [, $owner, , , $session] = $this->fixture();

        $this->actingAs($owner)->post($this->url($session, 'extend'), [
            'expected_lock_version' => 1,
            'extension_units' => 1,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('sessions.index', [
            'branch_id' => $session->branch_id,
            'status' => 'active',
        ]));

        $this->actingAs($owner)->post($this->url($session, 'cancel'), [
            'expected_lock_version' => 2,
            'reason' => 'Guardian requested an early exit.',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('sessions.index', [
            'branch_id' => $session->branch_id,
            'status' => 'cancelled',
        ]));
    }

    public function test_extend_rejects_non_thirty_minute_units_without_mutation(): void
    {
        [, $owner, , , $session] = $this->fixture();

        $this->actingAs($owner)->postJson($this->url($session, 'extend'), [
            'expected_lock_version' => 1,
            'added_minutes' => 45,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertUnprocessable();

        $this->assertSame(1, $session->fresh()->lock_version);
        $this->assertSame(0, DB::table('play_session_adjustments')->count());
        $this->assertSame(0, DB::table('play_session_commands')->count());
    }

    public function test_extend_alias_cannot_exceed_the_48_unit_contract(): void
    {
        [, $owner, , , $session] = $this->fixture();

        $this->actingAs($owner)->postJson($this->url($session, 'extend'), [
            'expected_lock_version' => 1,
            'added_minutes' => 1470,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertUnprocessable();

        $this->assertSame(1, $session->fresh()->lock_version);
        $this->assertSame(0, DB::table('play_session_adjustments')->count());
    }

    public function test_extend_replays_identical_key_and_conflicts_on_changed_payload(): void
    {
        [, $owner, , , $session] = $this->fixture();
        $key = (string) Str::uuid();
        $payload = ['expected_lock_version' => 1, 'extension_units' => 1, 'idempotency_key' => $key];

        $this->actingAs($owner)->postJson($this->url($session, 'extend'), $payload)->assertOk();
        $this->actingAs($owner)->postJson($this->url($session, 'extend'), $payload)
            ->assertOk()->assertJsonPath('created', false)->assertJsonPath('lock_version', 2);
        $this->actingAs($owner)->postJson($this->url($session, 'extend'), [
            ...$payload,
            'extension_units' => 2,
        ])->assertStatus(409);

        $this->assertSame(1, DB::table('play_session_commands')->count());
        $this->assertSame(1, DB::table('play_session_adjustments')->count());
    }

    public function test_cancel_requires_reason_and_sets_terminal_state_with_evidence(): void
    {
        [$tenant, $owner, , , $session] = $this->fixture();
        $key = (string) Str::uuid();

        $this->actingAs($owner)->postJson($this->url($session, 'cancel'), [
            'expected_lock_version' => 1,
            'reason' => 'Guardian requested an early exit.',
            'idempotency_key' => $key,
        ])->assertOk()->assertJsonPath('status', 'cancelled')->assertJsonPath('due_state', 'stopped');

        $updated = $session->fresh();
        $this->assertSame('cancelled', $updated->status);
        $this->assertSame('Guardian requested an early exit.', $updated->cancellation_reason);
        $this->assertNotNull($updated->ended_at);
        $this->assertDatabaseHas('play_session_events', ['session_id' => $session->id, 'event_type' => 'cancelled']);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'session.cancelled']);
    }

    public function test_cashier_is_denied_lifecycle_write_and_foreign_scope_is_hidden(): void
    {
        [$tenant, , , $cashier, $session] = $this->fixture();
        $this->actingAs($cashier)->postJson($this->url($session, 'extend'), [
            'expected_lock_version' => 1,
            'extension_units' => 1,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertForbidden();

        $foreign = $this->fixture()[4];
        $this->assertNotSame($tenant->id, $foreign->tenant_id);
        $this->actingAs($cashier)->getJson(route('sessions.state', ['session' => $foreign->id]))->assertNotFound();
    }

    public function test_state_derives_due_and_overdue_without_persisting_state(): void
    {
        [, $owner, , , $session] = $this->fixture();
        Carbon::setTestNow($session->expected_end_at);
        $this->actingAs($owner)->getJson(route('sessions.state', ['session' => $session->id]))
            ->assertOk()->assertJsonPath('due_state', 'due')->assertJsonPath('is_due', true)->assertJsonPath('is_overdue', false);

        Carbon::setTestNow($session->expected_end_at->addSecond());
        $this->actingAs($owner)->getJson(route('sessions.state', ['session' => $session->id]))
            ->assertOk()->assertJsonPath('due_state', 'overdue')->assertJsonPath('is_overdue', true);
        $this->assertSame('active', $session->fresh()->status);
    }

    public function test_family_profile_shows_scoped_visit_history(): void
    {
        [, $owner, , , $session] = $this->fixture();
        $foreignSession = $this->fixture()[4];

        $this->actingAs($owner)
            ->get(route('families.show', $session->guardian))
            ->assertOk()
            ->assertSee(__('families.visit_history_heading'))
            ->assertSee($session->child->full_name)
            ->assertSee($session->branch->name)
            ->assertDontSee($foreignSession->child->full_name)
            ->assertDontSee($foreignSession->branch->name);
    }

    public function test_mysql_parallel_lifecycle_commands_are_serialized_and_idempotent(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Real multi-connection lock evidence requires MySQL/InnoDB.');
        }
        $this->assertSame('testing', config('app.env'));
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->beforeApplicationDestroyed(fn () => RefreshDatabaseState::$migrated = false);
        Carbon::setTestNow('2026-09-13 12:00:00 UTC');

        [, $owner, , , $session] = $this->fixture();
        $extensionKey = (string) Str::uuid();
        $extensionResults = $this->competingLifecycle($owner, $session, 'extend', [
            'expected_lock_version' => 1,
            'extension_units' => 1,
            'idempotency_key' => $extensionKey,
        ]);

        $created = array_column($extensionResults, 'created');
        sort($created);
        $this->assertSame([false, true], $created);
        $this->assertSame(1, DB::table('play_session_commands')->where('command', 'extend')->count());
        $this->assertSame(1, DB::table('play_session_adjustments')->count());
        $this->assertSame(2, $session->fresh()->lock_version);

        $cancelResults = $this->competingLifecycle($owner, $session->fresh(), 'cancel', [
            'expected_lock_version' => 2,
            'reason' => 'Parallel terminal transition.',
            'idempotency_key' => (string) Str::uuid(),
        ], sameKey: false);
        $statuses = array_column($cancelResults, 'status');
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
        $this->assertSame('cancelled', $session->fresh()->status);
        $this->assertSame(1, DB::table('play_session_commands')->where('command', 'cancel')->count());
        $this->assertSame(1, DB::table('play_session_events')->where('event_type', 'cancelled')->count());
    }

    /** @return array{Tenant, User, User, User, PlaySession} */
    private function fixture(): array
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $manager = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($manager, ['tenant_id' => $tenant->id, 'role' => 'branch_manager', 'is_active' => true]);
        $branch->users()->attach($cashier, ['tenant_id' => $tenant->id, 'role' => 'cashier', 'is_active' => true]);
        $rule = PricingRule::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $owner->id,
            'base_price_minor' => 15000,
            'overtime_price_minor' => 7500,
            'tax_rate_bps' => 0,
        ]);
        $guardian = Guardian::factory()->create(['tenant_id' => $tenant->id, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        $child = Child::factory()->create(['tenant_id' => $tenant->id, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        DB::table('guardian_child')->insert([
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'relationship_type' => 'parent',
            'can_consent' => true,
            'can_check_out' => true,
            'is_active' => true,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $type = TicketType::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'pricing_rule_id' => $rule->id,
            'code' => 'LIFECYCLE-'.Str::upper(Str::random(8)), 'name' => 'Lifecycle test', 'price_minor' => 15000,
            'currency' => 'EGP', 'max_uses' => 1, 'status' => 'active', 'created_by_user_id' => $owner->id,
        ]);
        $now = now('UTC');
        $snapshot = [
            'pricing_rule_id' => $rule->id, 'base_duration_seconds' => 3600, 'price_minor' => 15000,
            'grace_period_seconds' => 600, 'overtime_unit_seconds' => 1800, 'overtime_price_minor' => 7500,
            'tax_rate_bps' => 0, 'tax_mode' => 'exclusive', 'currency' => 'EGP', 'branch_timezone' => $branch->timezone,
        ];
        $ticket = Ticket::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'ticket_type_id' => $type->id,
            'guardian_id' => $guardian->id, 'child_id' => $child->id, 'service_date' => $now->toDateString(),
            'status' => 'consumed', 'code_hash' => hash('sha256', Str::random(32)), 'code_payload_encrypted' => 'payload',
            'display_code' => 'PN-'.Str::upper(Str::random(8)), 'price_minor' => 15000, 'currency' => 'EGP',
            'price_snapshot_json' => $snapshot, 'issued_at' => $now, 'valid_from' => $now, 'valid_until' => $now->addDay(),
            'consumed_at' => $now, 'uses_count' => 1, 'max_uses' => 1, 'idempotency_key' => Str::uuid(),
            'issue_fingerprint' => hash('sha256', Str::random(32)), 'issued_by_user_id' => $owner->id, 'lock_version' => 1,
        ]);
        $startedAt = $now->subHour();
        $session = PlaySession::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'child_id' => $child->id, 'guardian_id' => $guardian->id,
            'ticket_id' => $ticket->id, 'pricing_rule_id' => $rule->id, 'status' => 'active', 'started_at' => $startedAt,
            'expected_end_at' => $startedAt->copy()->addHour(), 'pricing_snapshot_json' => $snapshot, 'created_by_user_id' => $owner->id, 'lock_version' => 1,
        ]);

        return [$tenant, $owner, $manager, $cashier, $session];
    }

    private function url(PlaySession $session, string $command): string
    {
        return route('sessions.'.$command, ['session' => $session->id]);
    }

    private function competingLifecycle(User $actor, PlaySession $session, string $command, array $payload, bool $sameKey = true): array
    {
        $worker = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Carbon::setTestNow('2026-09-13 12:00:00 UTC');
Carbon\CarbonImmutable::setTestNow('2026-09-13 12:00:00 UTC');
$actor = App\Models\User::query()->findOrFail($argv[2]);
$session = App\Models\PlaySession::query()->findOrFail($argv[3]);
$payload = json_decode($argv[4], true, flags: JSON_THROW_ON_ERROR);
$request = Illuminate\Http\Request::create('/app/sessions/'.$session->getKey().'/'.$argv[5], 'POST', $payload);
$request->headers->set('Accept', 'application/json');
$request->setUserResolver(fn () => $actor);
echo "ready\n";
try {
    $response = $app->make(App\Http\Controllers\SessionLifecycleController::class)->{$argv[5]}($request, $session);
    $status = $response->getStatusCode();
    $body = json_decode($response->getContent(), true) ?: [];
} catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
    $status = $exception->getStatusCode();
    $body = [];
}
echo json_encode(['status' => $status, 'created' => $body['created'] ?? null]);
PHP;
        $config = config('database.connections.mysql');
        $environment = [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
            'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_DATABASE' => $config['database'],
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'] ?? '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        ];
        $processes = [];
        DB::beginTransaction();
        try {
            DB::table('tenants')->where('id', $actor->tenant_id)->lockForUpdate()->first();
            for ($index = 0; $index < 2; $index++) {
                $processPayload = $payload;
                if (! $sameKey) {
                    $processPayload['idempotency_key'] = (string) Str::uuid();
                }
                $process = new Process([
                    PHP_BINARY, '-r', $worker, base_path(), (string) $actor->id,
                    (string) $session->id, json_encode($processPayload, JSON_THROW_ON_ERROR), $command,
                ], base_path(), $environment, timeout: 25);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (count(array_filter($processes, fn (Process $process): bool => str_contains($process->getOutput(), 'ready'))) < 2 && microtime(true) < $deadline) {
                usleep(20_000);
            }
            foreach ($processes as $process) {
                $this->assertStringContainsString('ready', $process->getOutput(), $process->getErrorOutput());
                $this->assertTrue($process->isRunning(), 'Both workers must contend on the held tenant lock.');
            }
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $results[] = json_decode(trim(str_replace("ready\n", '', $process->getOutput())), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }
}
