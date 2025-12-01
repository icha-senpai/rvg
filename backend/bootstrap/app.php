<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Only your custom middleware belongs here.
        $middleware->alias([
            'rank' => \App\Http\Middleware\RankMiddleware::class,
            'auth:sanctum' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'rsi.verified' => \App\Http\Middleware\EnsureRsiVerified::class,
        ]);

        // DO NOT re-alias Laravel auth middleware.
        // DO NOT alias Sanctum middleware.
        // DO NOT touch the 'api' or 'web' groups.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
        return response()->json([
            'message' => 'Unauthenticated.'
        ], 401);
    });

    $exceptions->renderable(function (\Laravel\Sanctum\Exceptions\MissingAbilityException $e, $request) {
        return response()->json([
            'message' => 'Unauthenticated.'
        ], 401);
    });
    })
    ->create();
