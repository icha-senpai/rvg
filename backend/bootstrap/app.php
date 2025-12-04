<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ForceDiscordAuth;

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {

        // 1. ALIASES
        $middleware->alias([
            'rank'               => \App\Http\Middleware\RankMiddleware::class,
            'auth:sanctum'       => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'rsi.verified'       => \App\Http\Middleware\EnsureRsiVerified::class,
            'verify.bot.secret'  => \App\Http\Middleware\VerifyBotSecret::class,
        ]);

        // 2. WEB GROUP — just attach both middlewares normally
        $middleware->web(append: [
            HandleInertiaRequests::class,
            ForceDiscordAuth::class,
        ]);

        // 3. API GROUP
        $middleware->api(append: [
            // nothing yet
        ]);
    })


    ->withExceptions(function (Exceptions $exceptions): void {

        // Sanctum + Auth exception uniform JSON handler
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
