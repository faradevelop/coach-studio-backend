
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

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
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        }
    });

    $exceptions->render(function (ModelNotFoundException $e, $request) {
        if ($request->is('api/*')) {
            return ApiResponse::error('Resource not found', [], 404);
        }
    });

    $exceptions->render(function (\Throwable $e, $request) {
        if ($request->is('api/*') && ! app()->hasDebugModeEnabled()) {
            return ApiResponse::error('Something went wrong', [], 500);
        }
    });

    })->create();
