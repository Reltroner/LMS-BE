<?php

namespace Tests\Unit;

use App\Http\Middleware\RequestIdMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddlewareTest extends TestCase
{
    public function test_case_1_missing_header_generates_valid_uuid(): void
    {
        $middleware = new RequestIdMiddleware();
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function (Request $req): Response {
            return new Response('OK', 200);
        });

        $attributeId = $request->attributes->get('request_id');
        $headerId = $response->headers->get('X-Request-ID');

        $this->assertNotNull($attributeId);
        $this->assertTrue(Str::isUuid((string) $attributeId));
        $this->assertSame($attributeId, $headerId);
    }

    public function test_case_2_valid_incoming_uuid_is_preserved_exactly(): void
    {
        $middleware = new RequestIdMiddleware();
        $incomingUuid = '018f5d85-7b3a-7c4e-8f7d-123456789abc';

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Request-ID', $incomingUuid);

        $response = $middleware->handle($request, function (Request $req): Response {
            return new Response('OK', 200);
        });

        $this->assertSame($incomingUuid, $request->attributes->get('request_id'));
        $this->assertSame($incomingUuid, $response->headers->get('X-Request-ID'));
    }

    public function test_case_3_invalid_incoming_value_is_replaced_with_valid_uuid(): void
    {
        $middleware = new RequestIdMiddleware();
        $invalidId = 'not-a-valid-request-id';

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Request-ID', $invalidId);

        $response = $middleware->handle($request, function (Request $req): Response {
            return new Response('OK', 200);
        });

        $attributeId = $request->attributes->get('request_id');
        $headerId = $response->headers->get('X-Request-ID');

        $this->assertNotSame($invalidId, $attributeId);
        $this->assertNotNull($attributeId);
        $this->assertTrue(Str::isUuid((string) $attributeId));
        $this->assertSame($attributeId, $headerId);
    }
}
