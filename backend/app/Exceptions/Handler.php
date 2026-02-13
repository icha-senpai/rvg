<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Render an unauthenticated response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated',
                'errors' => null,
            ], 401);
        }

        return redirect()->guest('/');
    }

    protected function invalidJson($request, ValidationException $exception)
    {
        return response()->json([
            'status' => 'error',
            'message' => $exception->getMessage(),
            'errors' => $exception->errors(),
        ], $exception->status);
    }

    public function report(Throwable $e): void
    {
        parent::report($e);
    }

    public function render($request, Throwable $e)
    {
        if (!$request->expectsJson() && !$request->is('api/*')) {
            $e = $this->prepareException($e);
            $status = 500;

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
            }

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
        }

        return parent::render($request, $e);
    }
}
