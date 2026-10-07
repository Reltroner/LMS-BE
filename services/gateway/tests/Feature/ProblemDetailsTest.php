<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class ProblemDetailsTest extends TestCase
{
    public function test_404_not_found_with_incoming_request_id(): void
    {
        $requestId = '018f5d85-7b3a-7c4e-8f7d-123456789abc';

        $response = $this->withHeaders([
            'X-Request-ID' => $requestId,
        ])->get('/definitely-missing');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Request-ID', $requestId);

        $response->assertExactJson([
            'type' => 'about:blank',
            'title' => 'Not Found',
            'status' => 404,
            'detail' => 'The requested resource was not found.',
            'code' => 'RESOURCE_NOT_FOUND',
            'request_id' => $requestId,
        ]);

        $response->assertJsonMissingPath('exception');
        $response->assertJsonMissingPath('trace');
        $response->assertJsonMissingPath('file');
        $response->assertJsonMissingPath('line');
    }

    public function test_404_not_found_generates_request_id_when_missing(): void
    {
        $response = $this->get('/definitely-missing');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('X-Request-ID');

        $headerRequestId = (string) $response->headers->get('X-Request-ID');
        $this->assertTrue(Str::isUuid($headerRequestId));

        $jsonRequestId = $response->json('request_id');
        $this->assertSame($headerRequestId, $jsonRequestId);
        $this->assertTrue(Str::isUuid($jsonRequestId));

        $response->assertJsonMissingPath('exception');
        $response->assertJsonMissingPath('trace');
        $response->assertJsonMissingPath('file');
        $response->assertJsonMissingPath('line');
    }

    public function test_405_method_not_allowed_preserves_request_id(): void
    {
        $requestId = '018f5d85-7b3a-7c4e-8f7d-123456789abc';

        $response = $this->withHeaders([
            'X-Request-ID' => $requestId,
        ])->post('/health/live');

        $response->assertStatus(405);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Request-ID', $requestId);

        $response->assertExactJson([
            'type' => 'about:blank',
            'title' => 'Method Not Allowed',
            'status' => 405,
            'detail' => 'The requested method is not allowed for this resource.',
            'code' => 'METHOD_NOT_ALLOWED',
            'request_id' => $requestId,
        ]);

        $response->assertJsonMissingPath('exception');
        $response->assertJsonMissingPath('trace');
        $response->assertJsonMissingPath('file');
        $response->assertJsonMissingPath('line');
    }
}
