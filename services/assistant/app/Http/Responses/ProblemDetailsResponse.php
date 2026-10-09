<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

final class ProblemDetailsResponse
{
    public static function make(
        Request $request,
        int $status,
        string $title,
        string $detail,
        string $code
    ): JsonResponse {
        $requestId = $request->attributes->get('request_id');

        if (!is_string($requestId) || $requestId === '') {
            throw new LogicException('Missing canonical request_id attribute on Request.');
        }

        return new JsonResponse([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'code' => $code,
            'request_id' => $requestId,
        ], $status, [
            'Content-Type' => 'application/problem+json',
            'Cache-Control' => 'no-store',
            'X-Request-ID' => $requestId,
        ]);
    }
}
