<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class HealthController
{
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'lms-learning',
            'check' => 'live',
        ], 200, [
            'Cache-Control' => 'no-store',
        ]);
    }

    public function ready(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'lms-learning',
            'check' => 'ready',
        ], 200, [
            'Cache-Control' => 'no-store',
        ]);
    }
}
