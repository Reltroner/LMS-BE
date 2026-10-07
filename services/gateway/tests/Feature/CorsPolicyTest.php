<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class CorsPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/v1/__test/cors', function () {
            return response()->json(['ok' => true]);
        });
    }

    public function test_learner_origin_actual_request(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://lms.reltroner.com',
        ])->get('/api/v1/__test/cors');

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://lms.reltroner.com');
        $this->assertStringContainsString('X-Request-ID', (string) $response->headers->get('Access-Control-Expose-Headers'));
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
        $response->assertHeader('X-Request-ID');
        $this->assertTrue(Str::isUuid((string) $response->headers->get('X-Request-ID')));
    }

    public function test_admin_origin_actual_request(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://lms-admin.reltroner.com',
        ])->get('/api/v1/__test/cors');

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://lms-admin.reltroner.com');
        $this->assertStringContainsString('X-Request-ID', (string) $response->headers->get('Access-Control-Expose-Headers'));
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
        $response->assertHeader('X-Request-ID');
        $this->assertTrue(Str::isUuid((string) $response->headers->get('X-Request-ID')));
    }

    public function test_disallowed_origin_actual_request(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://evil.example',
        ])->get('/api/v1/__test/cors');

        $response->assertStatus(200);
        $response->assertHeaderMissing('Access-Control-Allow-Origin');
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_allowed_preflight(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://lms.reltroner.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Authorization, Content-Type, X-Request-ID, Idempotency-Key',
        ])->options('/api/v1/__test/cors');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://lms.reltroner.com');

        $allowMethods = (string) $response->headers->get('Access-Control-Allow-Methods');
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            $this->assertStringContainsString($method, $allowMethods);
        }

        $allowHeaders = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));
        foreach (['authorization', 'content-type', 'x-request-id', 'idempotency-key'] as $header) {
            $this->assertStringContainsString($header, $allowHeaders);
        }

        $response->assertHeader('Access-Control-Max-Age', '600');
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');

        $response->assertHeader('X-Request-ID');
        $this->assertTrue(Str::isUuid((string) $response->headers->get('X-Request-ID')));
    }

    public function test_preflight_request_id_preservation(): void
    {
        $deterministicId = '018f5d85-7b3a-7c4e-8f7d-123456789abc';

        $response = $this->withHeaders([
            'Origin' => 'https://lms.reltroner.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Authorization, Content-Type, X-Request-ID, Idempotency-Key',
            'X-Request-ID' => $deterministicId,
        ])->options('/api/v1/__test/cors');

        $response->assertStatus(204);
        $response->assertHeader('X-Request-ID', $deterministicId);
    }

    public function test_disallowed_preflight(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://evil.example',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/__test/cors');

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
        $response->assertHeader('X-Request-ID');
        $this->assertTrue(Str::isUuid((string) $response->headers->get('X-Request-ID')));
    }

    public function test_health_routes_are_outside_cors_path(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://lms.reltroner.com',
        ])->get('/health/live');

        $response->assertStatus(200);
        $response->assertHeaderMissing('Access-Control-Allow-Origin');
        $response->assertHeader('X-Request-ID');
        $response->assertExactJson([
            'status' => 'ok',
            'service' => 'lms-gateway',
            'check' => 'live',
        ]);
    }
}
