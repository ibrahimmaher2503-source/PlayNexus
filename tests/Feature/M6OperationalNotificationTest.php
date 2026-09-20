<?php

namespace Tests\Feature;

use App\Jobs\ProcessOperationalNotification;
use App\Models\Branch;
use App\Models\NotificationMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class M6OperationalNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_duplicate_jobs_create_one_attempt_and_do_not_regress_sent_state(): void
    {
        $message = $this->message();
        $job = new ProcessOperationalNotification($message->id);
        $job->handle();
        $job->handle();

        $this->assertDatabaseHas('notification_messages', ['id' => $message->id, 'status' => 'sent', 'attempt_count' => 1]);
        $this->assertDatabaseCount('notification_attempts', 1);
        $this->assertNotNull($message->fresh()->sent_at);
        $this->assertNull($message->fresh()->delivered_at);
    }

    public function test_retryable_failures_are_bounded_and_become_terminal_without_losing_core_data(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00 UTC');
        config()->set('operational_notifications.local_outcome', 'retryable_failure');
        config()->set('operational_notifications.max_attempts', 3);
        $message = $this->message();
        $job = new ProcessOperationalNotification($message->id);
        $job->handle();
        $this->assertTrue($message->fresh()->scheduled_at->isFuture());
        Carbon::setTestNow(now()->addMinutes(1));
        $job->handle();
        Carbon::setTestNow(now()->addMinutes(2));
        $job->handle();
        $job->handle();

        $this->assertDatabaseHas('notification_messages', ['id' => $message->id, 'status' => 'failed_permanent', 'attempt_count' => 3]);
        $this->assertDatabaseCount('notification_attempts', 3);
        $this->assertDatabaseHas('tenants', ['id' => $message->tenant_id]);
    }

    public function test_encrypted_destination_is_never_exposed_by_the_status_page(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        NotificationMessage::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'channel' => 'database', 'purpose' => 'receipt', 'template_key' => 'receipt', 'template_version' => '1', 'locale' => 'ar', 'destination_encrypted' => '+201234567890', 'destination_masked' => '••••••••7890', 'payload_json' => ['order_id' => 1], 'status' => 'queued', 'dedupe_key' => 'receipt:1', 'scheduled_at' => now()]);

        $this->actingAs($owner)->get(route('notifications.index'))->assertOk()->assertSee('••••••••7890')->assertDontSee('+201234567890');
    }

    public function test_session_ending_intent_becomes_stale_when_the_session_is_no_longer_sendable(): void
    {
        $message = $this->message();
        $message->forceFill(['purpose' => 'session_ending', 'template_key' => 'session_ending', 'payload_json' => ['session_id' => 999, 'expected_end_at' => now('UTC')->toIso8601String()]])->save();

        (new ProcessOperationalNotification($message->id))->handle();

        $this->assertDatabaseHas('notification_messages', ['id' => $message->id, 'status' => 'stale', 'attempt_count' => 0]);
        $this->assertDatabaseCount('notification_attempts', 0);
    }

    public function test_reception_and_cashier_see_only_their_authorized_operational_purpose(): void
    {
        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $reception = User::factory()->create(['tenant_id' => $tenant->id]);
        $cashier = User::factory()->create(['tenant_id' => $tenant->id]);
        foreach ([[$reception, 'reception_staff'], [$cashier, 'cashier']] as [$user, $role]) {
            DB::table('branch_user')->insert(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'role' => $role, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (['session_ending', 'receipt'] as $purpose) {
            NotificationMessage::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'channel' => 'database', 'purpose' => $purpose, 'template_key' => $purpose, 'template_version' => '1', 'locale' => 'ar', 'destination_masked' => $purpose, 'payload_json' => [], 'status' => 'queued', 'dedupe_key' => $purpose, 'scheduled_at' => now()]);
        }

        $this->actingAs($reception)->get(route('notifications.index'))->assertOk()
            ->assertViewHas('messages', fn ($messages): bool => $messages->total() === 1 && $messages->first()->purpose === 'session_ending')
            ->assertViewHas('availablePurposes', ['session_ending']);
        $this->actingAs($cashier)->get(route('notifications.index'))->assertOk()
            ->assertViewHas('messages', fn ($messages): bool => $messages->total() === 1 && $messages->first()->purpose === 'receipt')
            ->assertViewHas('availablePurposes', ['receipt']);
    }

    private function message(): NotificationMessage
    {
        $tenant = Tenant::factory()->create();

        return NotificationMessage::query()->create(['tenant_id' => $tenant->id, 'channel' => 'database', 'purpose' => 'receipt', 'template_key' => 'receipt', 'template_version' => '1', 'locale' => 'ar', 'destination_encrypted' => '+201234567890', 'destination_masked' => '••••••••7890', 'payload_json' => ['order_id' => 1], 'status' => 'queued', 'dedupe_key' => 'receipt:1', 'scheduled_at' => now()]);
    }
}
