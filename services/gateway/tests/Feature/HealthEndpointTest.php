<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_live_endpoint(): void
    {
        $response = $this->get('/health/live');

        $response->assertStatus(200);
        $response->assertExactJson([
            'status' => 'ok',
            'service' => 'lms-gateway',
            'check' => 'live',
        ]);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_health_ready_endpoint(): void
    {
        $response = $this->get('/health/ready');

        $response->assertStatus(200);
        $response->assertExactJson([
            'status' => 'ok',
            'service' => 'lms-gateway',
            'check' => 'ready',
        ]);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
