<?php

namespace Tests\Feature;

use App\Actions\ProcessCashRefund;
use App\Models\Branch;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Order;
use App\Models\PlaySession;
use App\Models\PricingRule;
use App\Models\Refund;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class M5FinancialConcurrencyTest extends TestCase
{
    public function test_mysql_concurrent_settlement_and_refund_retries_commit_once(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Real financial lock evidence requires MySQL/InnoDB.');
        }

        $this->assertSame('testing', config('app.env'));
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->beforeApplicationDestroyed(fn () => RefreshDatabaseState::$migrated = false);
        CarbonImmutable::setTestNow('2026-09-14 10:00:00 UTC');

        [$tenant, $branch, $cashier, $manager, $session] = $this->pendingSession();
        $settlementKey = (string) Str::uuid();
        $settlements = $this->compete($tenant, $cashier, 'settle', $session->id, [
            'expected_lock_version' => 2,
            'amount_minor' => 25650,
            'currency' => 'EGP',
            'idempotency_key' => $settlementKey,
        ]);

        $statuses = array_column($settlements, 'status');
        sort($statuses);
        $this->assertSame([200, 201], $statuses);
        $this->assertSame($settlements[0]['order_id'], $settlements[1]['order_id']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(1, DB::table('play_session_events')->where('event_type', 'completed')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'session.cash_settled')->count());
        $this->assertSame('completed', $session->fresh()->status);

        $order = Order::query()->findOrFail($settlements[0]['order_id']);
        $refund = app(ProcessCashRefund::class)->request(
            $cashier,
            $tenant,
            $order,
            2,
            'Concurrent retry evidence',
            (string) Str::uuid(),
            (string) Str::uuid(),
        );
        app(ProcessCashRefund::class)->approve(
            $manager,
            $tenant,
            Refund::query()->findOrFail($refund['refund_id']),
            (string) Str::uuid(),
        );

        $executionKey = (string) Str::uuid();
        $executions = $this->compete($tenant, $cashier, 'refund', (int) $refund['refund_id'], [
            'expected_order_lock_version' => 2,
            'idempotency_key' => $executionKey,
        ]);

        $this->assertSame([200, 200], array_column($executions, 'status'));
        $created = array_column($executions, 'created');
        sort($created);
        $this->assertSame([false, true], $created);
        $this->assertDatabaseCount('refunds', 1);
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(25650, $order->fresh()->refunded_minor);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'order.refund_executed')->count());
        $this->assertSame(1, DB::table('payments')->where('branch_id', $branch->id)->count());
    }

    private function compete(Tenant $tenant, User $actor, string $action, int $subjectId, array $payload): array
    {
        $worker = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Carbon::setTestNow('2026-09-14 10:00:00 UTC');
Carbon\CarbonImmutable::setTestNow('2026-09-14 10:00:00 UTC');
$actor = App\Models\User::query()->findOrFail($argv[2]);
$payload = json_decode($argv[3], true);
$request = Illuminate\Http\Request::create('/app/m5-concurrency', 'POST', $payload);
$request->headers->set('Accept', 'application/json');
$request->setUserResolver(fn () => $actor);
echo "ready\n";
if ($argv[4] === 'settle') {
    $subject = App\Models\PlaySession::query()->findOrFail($argv[5]);
    $response = $app->make(App\Http\Controllers\CashSettlementController::class)
        ->store($request, $subject, $app->make(App\Actions\SettlePendingSession::class));
} else {
    $response = $app->make(App\Http\Controllers\CashRefundController::class)
        ->execute($request, (int) $argv[5], $app->make(App\Actions\ProcessCashRefund::class));
}
$body = json_decode($response->getContent(), true);
echo json_encode([
    'status' => $response->getStatusCode(),
    'created' => $body['created'] ?? null,
    'order_id' => $body['order_id'] ?? null,
]);
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
            DB::table('tenants')->where('id', $tenant->id)->lockForUpdate()->first();
            for ($index = 0; $index < 2; $index++) {
                $process = new Process([
                    PHP_BINARY, '-r', $worker, base_path(), (string) $actor->id,
                    json_encode($payload, JSON_THROW_ON_ERROR), $action, (string) $subjectId,
                ], base_path(), $environment, timeout: 25);
                $process->start();
                $processes[] = $process;
            }

            $deadline = microtime(true) + 10;
            while (count(array_filter($processes, fn (Process $process) => str_contains($process->getOutput(), 'ready'))) < 2 && microtime(true) < $deadline) {
                usleep(20000);
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

    private function pendingSession(): array
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
        $branch = Branch::factory()->create([
            'tenant_id' => $tenant->id, 'timezone' => 'Africa/Cairo', 'receipt_prefix' => 'PN',
            'currency' => 'EGP', 'payment_methods' => ['cash'], 'is_active' => true,
        ]);
        $cashier = $this->staff($tenant, $branch, 'cashier');
        $manager = $this->staff($tenant, $branch, 'branch_manager');
        $rule = PricingRule::factory()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id,
            'created_by_user_id' => $owner->id, 'currency' => 'EGP',
        ]);
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id,
        ]);
        $child = Child::factory()->create([
            'tenant_id' => $tenant->id, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id,
        ]);
        $type = TicketType::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'pricing_rule_id' => $rule->id,
            'code' => 'M5-RACE', 'name' => 'Concurrent settlement', 'price_minor' => 15000,
            'currency' => 'EGP', 'max_uses' => 1, 'status' => 'active', 'created_by_user_id' => $owner->id,
        ]);
        $ticket = Ticket::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'ticket_type_id' => $type->id,
            'guardian_id' => $guardian->id, 'child_id' => $child->id, 'service_date' => '2026-09-14',
            'status' => 'consumed', 'code_hash' => hash('sha256', 'm5-race'), 'code_payload_encrypted' => 'opaque',
            'display_code' => 'PN-M5-RACE', 'price_minor' => 15000, 'currency' => 'EGP',
            'price_snapshot_json' => ['pricing_rule_id' => $rule->id, 'branch_timezone' => 'Africa/Cairo'],
            'issued_at' => now(), 'valid_from' => now(), 'valid_until' => now()->addHour(), 'consumed_at' => now(),
            'uses_count' => 1, 'max_uses' => 1, 'idempotency_key' => (string) Str::uuid(),
            'issue_fingerprint' => hash('sha256', 'm5-race-issue'), 'issued_by_user_id' => $owner->id, 'lock_version' => 2,
        ]);
        $started = CarbonImmutable::parse('2026-09-14 08:00:00 UTC');
        $quote = [
            'elapsed_seconds' => 4201, 'included_seconds' => 4200, 'overtime_seconds' => 1,
            'overtime_units' => 1, 'subtotal_minor' => 22500, 'tax_minor' => 3150,
            'total_minor' => 25650, 'tax_rate_bps' => 1400, 'tax_mode' => 'exclusive', 'currency' => 'EGP',
        ];
        $session = PlaySession::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'child_id' => $child->id,
            'guardian_id' => $guardian->id, 'ticket_id' => $ticket->id, 'pricing_rule_id' => $rule->id,
            'status' => 'pending_payment', 'started_at' => $started, 'expected_end_at' => $started->addHour(),
            'pricing_snapshot_json' => ['currency' => 'EGP'], 'created_by_user_id' => $owner->id, 'lock_version' => 2,
            'checkout_guardian_id' => $guardian->id, 'checkout_verification_method' => 'phone_last_four',
            'checkout_verified_by_user_id' => $owner->id, 'checkout_verified_at' => $started,
            'checkout_prepared_at' => $started, 'checkout_snapshot_json' => $quote, 'checkout_amount_due_minor' => 25650,
        ]);

        return [$tenant, $branch, $cashier, $manager, $session];
    }

    private function staff(Tenant $tenant, Branch $branch, string $role): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $branch->users()->attach($user, ['tenant_id' => $tenant->id, 'role' => $role, 'is_active' => true]);

        return $user;
    }
}
