<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_live_endpoint(): void
    {
        $response = $this->get('/health/live');

        $response->assertStatus(200);
        $response->assertExactJson([
            'status' => 'ok',
            'service' => 'lms-assistant',
            'check' => 'live',
        ]);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Request-ID');
        $this->assertTrue(Str::isUuid((string) $response->headers->get('X-Request-ID')));
    }

    public function test_health_ready_endpoint(): void
    {
        $response = $this->get('/health/ready');

        $response->assertStatus(200);
        $response->assertExactJson([
            'status' => 'ok',
            'service' => 'lms-assistant',
            'check' => 'ready',
        ]);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Request-ID');
        $this->assertTrue(Str::isUuid((string) $response->headers->get('X-Request-ID')));
    }

    public function test_health_ready_with_known_valid_uuid_header(): void
    {
        $incomingUuid = '018f5d85-7b3a-7c4e-8f7d-123456789abc';

        $response = $this->withHeaders([
            'X-Request-ID' => $incomingUuid,
        ])->get('/health/ready');

        $response->assertStatus(200);
        $response->assertExactJson([
            'status' => 'ok',
            'service' => 'lms-assistant',
            'check' => 'ready',
        ]);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Request-ID', $incomingUuid);
    }

    public function test_health_endpoint_does_not_produce_cors_header(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://lms.reltroner.com',
        ])->get('/health/live');

        $response->assertStatus(200);
        $response->assertHeaderMissing('Access-Control-Allow-Origin');
        $response->assertExactJson([
            'status' => 'ok',
            'service' => 'lms-assistant',
            'check' => 'live',
        ]);
    }
}
