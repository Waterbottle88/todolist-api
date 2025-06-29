<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_successful_response(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
    }

    public function test_health_endpoint_works_without_authentication(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
    }

    public function test_health_endpoint_accepts_get_method_only(): void
    {
        $response = $this->postJson('/api/health');
        $response->assertStatus(405);

        $response = $this->putJson('/api/health');
        $response->assertStatus(405);

        $response = $this->deleteJson('/api/health');
        $response->assertStatus(405);

        $response = $this->patchJson('/api/health');
        $response->assertStatus(405);
    }

    public function test_health_endpoint_response_format(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_health_check_can_be_called_multiple_times(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->getJson('/api/health');
            $response->assertStatus(200);
        }
    }

    public function test_health_endpoint_with_accept_header(): void
    {
        $response = $this->getJson('/api/health', [
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_health_endpoint_performance(): void
    {
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/health');
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);
        
        $this->assertLessThan(1.0, $executionTime, 'Health check should respond within 1 second');
    }
}