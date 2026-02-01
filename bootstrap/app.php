<?php

use App\Exceptions\TaskException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (TaskException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'message' => $e->getMessage(),
                ], $e->getStatusCode());
            }
        });

        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'message' => 'The provided data is invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->renderable(function (\Throwable $e, Request $request) {
            if ($request->expectsJson() && !$e instanceof ValidationException && !$e instanceof TaskException) {
                Log::error('Unexpected error', [
                    'user_id' => auth()->id(),
                    'url' => $request->fullUrl(),
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'error' => 'Internal server error',
                    'message' => 'An unexpected error occurred.',
                ], 500);
            }
        });
    })->create();
