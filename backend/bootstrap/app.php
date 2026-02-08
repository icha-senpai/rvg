<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ForceDiscordAuth;
use App\Http\Middleware\EnforceMaxAuthAge;

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {

        /*
        |--------------------------------------------------------------------------
        | Guest Redirection (Laravel 11+ replacement for Authenticate.php)
        |--------------------------------------------------------------------------
        | - Browsers are redirected to /verify (Discord auth entry)
        | - API / JSON requests receive a clean 401 instead of a redirect
        */
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->expectsJson()) {
                return null;
            }

            return '/verify';
        });

        /*
        |--------------------------------------------------------------------------
        | Middleware Aliases
        |--------------------------------------------------------------------------
        */
        $middleware->alias([
            'rank'               => \App\Http\Middleware\RankMiddleware::class,
            'auth:sanctum'       => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'rsi.verified'       => \App\Http\Middleware\EnsureRsiVerified::class,
            'verify.bot.secret'  => \App\Http\Middleware\VerifyBotSecret::class,
            'Authority'          => \App\Domain\AccessControl\Facades\Authority::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Web Middleware Group
        |--------------------------------------------------------------------------
        | - Inertia request handling
        | - Forced Discord authentication for all web routes
        */
        $middleware->web(append: [
            HandleInertiaRequests::class,
            ForceDiscordAuth::class,
            EnforceMaxAuthAge::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | API Middleware Group
        |--------------------------------------------------------------------------
        | - Intentionally minimal for now
        | - Auth failures handled via exception renderers below
        */
        $middleware->api(append: [
            // (empty by design)
        ]);
    })

    ->withProviders([
        \App\Providers\AccessControlServiceProvider::class,
        \App\Providers\DomainEventServiceProvider::class,
    ])

    ->withExceptions(function (Exceptions $exceptions): void {

        /*
        |--------------------------------------------------------------------------
        | Authentication / Sanctum Exception Normalization
        |--------------------------------------------------------------------------
        | Ensures APIs and bots always receive JSON 401 responses
        */
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        });

        $exceptions->renderable(function (\Laravel\Sanctum\Exceptions\MissingAbilityException $e, $request) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        });
    })

    ->create();
