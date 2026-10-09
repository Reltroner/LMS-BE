<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tests\TestCase;

class InternalCorsIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get('/api/v1/__test/no-cors', function () {
            return response()->json(['ok' => true]);
        });

        Route::middleware('api')->options('/api/v1/__test/no-cors', function () {
            throw new MethodNotAllowedHttpException(['GET']);
        });
    }

    public function test_actual_cross_origin_request(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://lms.reltroner.com',
        ])->get('/api/v1/__test/no-cors');

        $response->assertStatus(200);
        $response->assertExactJson([
            'ok' => true,
        ]);

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
        $response->assertHeaderMissing('Access-Control-Expose-Headers');

        $response->assertHeader('X-Request-ID');
        $this->assertTrue(Str::isUuid((string) $response->headers->get('X-Request-ID')));
    }

    public function test_preflight_like_request(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://lms.reltroner.com',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization, Content-Type, X-Request-ID',
        ])->options('/api/v1/__test/no-cors');

        $response->assertStatus(405);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertSame('METHOD_NOT_ALLOWED', $response->json('code'));

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
        $response->assertHeaderMissing('Access-Control-Allow-Methods');
        $response->assertHeaderMissing('Access-Control-Allow-Headers');
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
        $response->assertHeaderMissing('Access-Control-Max-Age');

        $response->assertHeader('X-Request-ID');
        $this->assertTrue(Str::isUuid((string) $response->headers->get('X-Request-ID')));
    }
}
