<?php

namespace App\Http\Middleware;

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
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Check RSI verification status
        if (! $user->rsi_verified_at) {
            return response()->json([
                'message' => 'RSI verification required.',
                'status'  => 'rsi_unverified',
            ], 403);
        }

        return $next($request);
    }
}
