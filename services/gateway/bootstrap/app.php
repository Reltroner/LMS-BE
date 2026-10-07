<?php

use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Responses\ProblemDetailsResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function () {
            Route::middleware('api')
                ->group(base_path('routes/health.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequestIdMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return ProblemDetailsResponse::make(
                request: $request,
                status: 404,
                title: 'Not Found',
                detail: 'The requested resource was not found.',
                code: 'RESOURCE_NOT_FOUND',
            );
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            return ProblemDetailsResponse::make(
                request: $request,
                status: 405,
                title: 'Method Not Allowed',
                detail: 'The requested method is not allowed for this resource.',
                code: 'METHOD_NOT_ALLOWED',
            );
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            return ProblemDetailsResponse::make(
                request: $request,
                status: 500,
                title: 'Internal Server Error',
                detail: 'An unexpected error occurred.',
                code: 'INTERNAL_SERVER_ERROR',
            );
        });
    })->create();
