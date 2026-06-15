<?php

use App\Helpers\WebAuthRedirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ApplyRolePreview;
use App\Http\Middleware\ForceDiscordAuth;
use App\Http\Middleware\EnforceMaxAuthAge;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            if (WebAuthRedirect::shouldReturnJson($request)) {
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
            ApplyRolePreview::class,
            HandleInertiaRequests::class,
            ForceDiscordAuth::class,
            EnforceMaxAuthAge::class,
        ]);

        $middleware->appendToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            ApplyRolePreview::class,
        );

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
            if (! WebAuthRedirect::shouldReturnJson($request)) {
                return WebAuthRedirect::redirectToVerify($request);
            }

            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        });

        $exceptions->renderable(function (\Laravel\Sanctum\Exceptions\MissingAbilityException $e, $request) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        });

        $exceptions->renderable(function (ValidationException $e, $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        });

        /*
        |---------------------------------------------------------------
        | Horizon Inertia Error Screens (Web only)
        |---------------------------------------------------------------
        */
        $exceptions->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            Inertia::setRootView('app');

            return Inertia::render('Error', [
                'status' => 404,
            ])->toResponse($request)->setStatusCode(404);
        });

        $exceptions->renderable(function (TokenMismatchException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            Inertia::setRootView('app');

            return Inertia::render('Error', [
                'status' => 419,
            ])->toResponse($request)->setStatusCode(419);
        });

        $exceptions->renderable(function (HttpExceptionInterface $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();
            $alwaysRender = [403, 404, 419, 429];
            $renderWhenNotDebug = [500, 503];

            if (
                in_array($status, $alwaysRender, true)
                || (!config('app.debug') && in_array($status, $renderWhenNotDebug, true))
            ) {
                Inertia::setRootView('app');

                return Inertia::render('Error', [
                    'status' => $status,
                ])->toResponse($request)->setStatusCode($status);
            }

            return null;
        });
    })

    ->create();
