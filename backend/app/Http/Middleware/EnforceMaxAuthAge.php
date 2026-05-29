<?php

namespace App\Http\Middleware;

use App\Helpers\WebAuthRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class EnforceMaxAuthAge
{
    public function handle(Request $request, Closure $next)
    {
        if (WebAuthRedirect::shouldIgnore($request)) {
            return $next($request);
        }

        if (! Auth::check()) {
            return $next($request);
        }

        $startedAtRaw = $request->session()->get('hz_auth_started_at');

        if (! $startedAtRaw) {
            $request->session()->put('hz_auth_started_at', now()->toIso8601String());
            return $next($request);
        }

        try {
            $startedAt = Carbon::parse($startedAtRaw);
        } catch (\Throwable $e) {
            $request->session()->put('hz_auth_started_at', now()->toIso8601String());
            return $next($request);
        }

        if ($startedAt->lt(now()->subDays(7))) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if (WebAuthRedirect::shouldReturnJson($request)) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return WebAuthRedirect::redirectToVerify($request);
        }

        return $next($request);
    }
}
