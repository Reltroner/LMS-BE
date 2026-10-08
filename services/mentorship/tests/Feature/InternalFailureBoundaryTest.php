<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class InternalFailureBoundaryTest extends TestCase
{
    private const SENTINEL_MESSAGE = 'SENSITIVE_INTERNAL_MESSAGE_SHOULD_NEVER_LEAK';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__test/internal-failure', function () {
            throw new RuntimeException(self::SENTINEL_MESSAGE);
        });
    }

    public function test_500_internal_server_error_with_existing_request_id(): void
    {
        $requestId = '018f5d85-7b3a-7c4e-8f7d-123456789abc';

        $response = $this->withHeaders([
            'X-Request-ID' => $requestId,
        ])->get('/__test/internal-failure');

        $response->assertStatus(500);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Request-ID', $requestId);

        $response->assertExactJson([
            'type' => 'about:blank',
            'title' => 'Internal Server Error',
            'status' => 500,
            'detail' => 'An unexpected error occurred.',
            'code' => 'INTERNAL_SERVER_ERROR',
            'request_id' => $requestId,
        ]);

        $this->assertStringNotContainsString(self::SENTINEL_MESSAGE, $response->getContent());
        $response->assertJsonMissingPath('exception');
        $response->assertJsonMissingPath('trace');
        $response->assertJsonMissingPath('file');
        $response->assertJsonMissingPath('line');
    }

    public function test_500_internal_server_error_generates_request_id_when_missing(): void
    {
        $response = $this->get('/__test/internal-failure');

        $response->assertStatus(500);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('X-Request-ID');

        $headerRequestId = (string) $response->headers->get('X-Request-ID');
        $this->assertTrue(Str::isUuid($headerRequestId));

        $jsonRequestId = $response->json('request_id');
        $this->assertSame($headerRequestId, $jsonRequestId);
        $this->assertTrue(Str::isUuid($jsonRequestId));

        $this->assertSame('INTERNAL_SERVER_ERROR', $response->json('code'));
        $this->assertStringNotContainsString(self::SENTINEL_MESSAGE, $response->getContent());
        $response->assertJsonMissingPath('exception');
        $response->assertJsonMissingPath('trace');
        $response->assertJsonMissingPath('file');
        $response->assertJsonMissingPath('line');
    }
}
