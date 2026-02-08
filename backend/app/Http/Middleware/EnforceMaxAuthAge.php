<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class EnforceMaxAuthAge
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('verify') || $request->is('verify/*')) {
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

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return redirect('/verify');
        }

        return $next($request);
    }
}
