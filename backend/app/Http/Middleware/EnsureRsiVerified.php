<?php

namespace App\Http\Middleware;

use App\Helpers\WebAuthRedirect;
use Closure;
use Illuminate\Http\Request;

class EnsureRsiVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Not logged in
        if (! $user) {
            if (! WebAuthRedirect::shouldReturnJson($request)) {
                return WebAuthRedirect::redirectToVerify($request);
            }

            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Check RSI verification status
        if (! $user->rsi_verified_at) {
            if (! WebAuthRedirect::shouldReturnJson($request)) {
                return WebAuthRedirect::redirectToVerify($request);
            }

            return response()->json([
                'message' => 'RSI verification required.',
                'status'  => 'rsi_unverified',
            ], 403);
        }

        return $next($request);
    }
}
