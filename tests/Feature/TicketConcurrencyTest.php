<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Support\FamilyConsentRecorder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TicketConcurrencyTest extends TestCase
{
    public function test_mysql_simultaneous_check_in_consumes_ticket_once_and_commits_one_session(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Real multi-connection lock evidence requires MySQL/InnoDB.');
        }
        $this->assertSame('testing', config('app.env'));
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->beforeApplicationDestroyed(fn () => RefreshDatabaseState::$migrated = false);
        Carbon::setTestNow('2026-09-13 12:00:00 UTC');

        $tenant = Tenant::factory()->create(['is_active' => true]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $actor->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Africa/Cairo', 'capacity' => 5]);
        DB::table('branch_opening_hours')->insert([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'weekday' => 7,
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:59',
            'is_closed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rule = PricingRule::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'created_by_user_id' => $actor->id]);
        $type = TicketType::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'pricing_rule_id' => $rule->id,
            'code' => 'CONCURRENT-CHECKIN', 'name' => 'Concurrent check-in', 'price_minor' => 15000,
            'currency' => 'EGP', 'status' => 'active', 'max_uses' => 1, 'created_by_user_id' => $actor->id,
        ]);
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id, 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id,
        ]);
        $child = Child::factory()->create([
            'tenant_id' => $tenant->id, 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id,
            'emergency_contact_name' => 'QA parent', 'emergency_contact_phone_e164' => '+201001112233',
        ]);
        $guardian->children()->attach($child->id, [
            'tenant_id' => $tenant->id, 'relationship_type' => 'legal_guardian', 'can_consent' => true,
            'can_check_out' => true, 'verified_at' => now(), 'is_active' => true,
            'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id,
        ]);
        FamilyConsentRecorder::record($actor, $tenant, $guardian, $child, 'child_data', 'granted', 'ar', (string) Str::uuid());

        $issue = $this->competingCommands($actor, 'issue', [
            'branch_id' => $branch->id, 'ticket_type_id' => $type->id, 'guardian_id' => $guardian->id,
            'child_id' => $child->id, 'service_date' => '2026-09-13', 'idempotency_key' => (string) Str::uuid(),
        ]);
        $issueStatuses = array_column($issue, 'status');
        sort($issueStatuses);
        $this->assertSame([200, 201], $issueStatuses);
        $ticket = Ticket::query()->firstOrFail();

        $checkIns = $this->competingCommands($actor, 'checkIn', [
            'branch_id' => $branch->id, 'code' => $ticket->display_code,
        ]);
        $statuses = array_column($checkIns, 'status');
        sort($statuses);
        $results = array_column($checkIns, 'result');
        sort($results);
        $this->assertSame([201, 422], $statuses);
        $this->assertSame(['accepted', 'already_consumed'], $results);
        $this->assertDatabaseCount('play_sessions', 1);
        $this->assertDatabaseCount('play_session_events', 1);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'session.checked_in')->count());
        $this->assertSame(1, $ticket->fresh()->uses_count);
        $this->assertSame(2, $ticket->fresh()->lock_version);
        $this->assertSame(2, DB::table('ticket_scans')->where('scan_purpose', 'check_in')->count());
        $storedResults = DB::table('ticket_scans')->where('scan_purpose', 'check_in')->pluck('result')->all();
        sort($storedResults);
        $this->assertSame(['accepted', 'already_consumed'], $storedResults);
    }

    public function test_mysql_simultaneous_issue_and_scan_retry_commit_only_once(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Real multi-connection lock evidence requires MySQL/InnoDB.');
        }
        $this->assertSame('testing', config('app.env'));
        // Committed fixtures are necessary across processes; existing settings migrations are forward-only.
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->beforeApplicationDestroyed(fn () => RefreshDatabaseState::$migrated = false);
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $actor->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Africa/Cairo']);
        DB::table('branch_opening_hours')->updateOrInsert([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'weekday' => 7,
        ], ['opens_at' => '00:00:00', 'closes_at' => '23:59:59', 'is_closed' => false]);
        $rule = PricingRule::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'created_by_user_id' => $actor->id]);
        $type = TicketType::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'pricing_rule_id' => $rule->id,
            'code' => 'CONCURRENT', 'name' => 'Concurrent test', 'price_minor' => 15000,
            'currency' => 'EGP', 'status' => 'active', 'max_uses' => 1, 'created_by_user_id' => $actor->id,
        ]);
        $guardian = Guardian::factory()->create(['tenant_id' => $tenant->id, 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id]);
        $child = Child::factory()->create([
            'tenant_id' => $tenant->id, 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id,
            'emergency_contact_name' => 'QA parent', 'emergency_contact_phone_e164' => '+201001112233',
        ]);
        $guardian->children()->attach($child->id, [
            'tenant_id' => $tenant->id, 'relationship_type' => 'legal_guardian', 'can_consent' => true,
            'can_check_out' => true, 'verified_at' => now(), 'is_active' => true,
            'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id,
        ]);
        FamilyConsentRecorder::record($actor, $tenant, $guardian, $child, 'child_data', 'granted', 'ar', (string) Str::uuid());
        $issue = $this->competingCommands($actor, 'issue', [
            'branch_id' => $branch->id, 'ticket_type_id' => $type->id, 'guardian_id' => $guardian->id,
            'child_id' => $child->id, 'service_date' => '2026-09-13', 'idempotency_key' => (string) Str::uuid(),
        ]);
        $statuses = array_column($issue, 'status');
        sort($statuses);
        $this->assertSame([200, 201], $statuses);
        $this->assertSame($issue[0]['ticket_id'], $issue[1]['ticket_id']);
        $this->assertDatabaseCount('tickets', 1);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'ticket.issued')->count());
        $ticket = Ticket::query()->firstOrFail();
        $scan = $this->competingCommands($actor, 'scan', [
            'branch_id' => $branch->id, 'code' => $ticket->display_code, 'idempotency_key' => (string) Str::uuid(),
        ]);
        $this->assertSame([200, 200], array_column($scan, 'status'));
        $this->assertSame(['accepted', 'accepted'], array_column($scan, 'result'));
        $this->assertDatabaseCount('ticket_scans', 1);
        $this->assertSame(2, $ticket->fresh()->lock_version);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'ticket.assignment.locked')->count());
    }

    private function competingCommands(User $actor, string $action, array $payload): array
    {
        // Invoke the real transactional controller in two PHP processes; HTTP middleware has separate feature coverage.
        $worker = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Carbon::setTestNow('2026-09-13 12:00:00 UTC');
Carbon\CarbonImmutable::setTestNow('2026-09-13 12:00:00 UTC');
$actor = App\Models\User::query()->findOrFail($argv[2]);
$request = Illuminate\Http\Request::create($argv[4] === 'checkIn' ? '/app/sessions/check-in' : '/app/tickets', 'POST', json_decode($argv[3], true));
$request->headers->set('Accept', 'application/json');
$request->setUserResolver(fn () => $actor);
echo "ready\n";
$controller = $argv[4] === 'checkIn' ? App\Http\Controllers\PlaySessionController::class : App\Http\Controllers\TicketController::class;
$response = $app->make($controller)->{$argv[4]}($request);
$body = json_decode($response->getContent(), true);
echo json_encode(['status'=>$response->getStatusCode(), 'ticket_id'=>$body['ticket_id']??null, 'result'=>$body['result']??null]);
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
                if ($action === 'checkIn') {
                    $processPayload['idempotency_key'] = (string) Str::uuid();
                }
                $process = new Process([PHP_BINARY, '-r', $worker, base_path(), (string) $actor->id, json_encode($processPayload, JSON_THROW_ON_ERROR), $action], base_path(), $environment, timeout: 25);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (count(array_filter($processes, fn (Process $p) => str_contains($p->getOutput(), 'ready'))) < 2 && microtime(true) < $deadline) {
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
}
