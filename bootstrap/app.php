<?php

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {

        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    'Validation failed',
                    $e->errors(),
                    422
                );
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    'Resource not found',
                    [],
                    404
                );
            }
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    'Unauthenticated',
                    [],
                    401
                );
            }
        });

        // AuthorizationException must be handled separately.
        $exceptions->render(function (AuthorizationException $e, $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    'This action is unauthorized.',
                    [],
                    403
                );
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    $e->getMessage() ?: 'Request failed',
                    [],
                    $e->getStatusCode()
                );
            }
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    'Something went wrong',
                    [],
                    500
                );
            }
        });
    })
    ->create();
