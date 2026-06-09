<?php

use App\Contexts\Catalog\Domain\Course\InvalidCourseTransition;
use App\Http\Middleware\EnsurePaymentsEnabled;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'payments.enabled' => EnsurePaymentsEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Domain rule violations surface as 422 on the API rather than 500.
        $exceptions->render(function (InvalidCourseTransition $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return null;
        });
    })->create();
