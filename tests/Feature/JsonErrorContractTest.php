<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JsonErrorContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_errors_include_a_stable_code_and_server_request_id(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $guardian = Guardian::factory()->create(['tenant_id' => $tenant->id]);

        foreach ([
            $this->getJson(route('families.index')), // unauthenticated
            $this->actingAs($user)->getJson(route('families.show', $guardian)), // forbidden
            $this->getJson('/app/families/999999'), // not found
        ] as $response) {
            $response->assertJsonStructure(['code', 'message', 'request_id']);
            $this->assertSame($response->json('request_id'), $response->headers->get('X-Request-ID'));
            $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->json('request_id'));
        }
    }
}
