<?php

namespace Tests\Feature;

use Database\Seeders\M6PilotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class M6PilotSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_seed_is_synthetic_idempotent_and_report_ready(): void
    {
        $this->seed(M6PilotSeeder::class);
        $this->seed(M6PilotSeeder::class);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('play_sessions', 2);
        $this->assertDatabaseCount('notification_messages', 2);
        $this->assertDatabaseHas('guardians', ['full_name' => '[DEMO] Pilot Guardian']);
        $this->assertDatabaseMissing('guardians', ['full_name' => 'Real Child']);
    }
}
