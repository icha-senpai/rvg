<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

class ForceDiscordAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Routes that must NOT enforce Discord auth.
        $exclude = [
            'verify',
            'verify/*',
            'auth/discord',
            'auth/discord/*',
            'api/v1/auth/discord',
            'api/v1/auth/discord/*',
        ];

        // Check request path against exclusions
        foreach ($exclude as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        // If user is NOT logged in → send them to Discord OAuth
        if (!Auth::check()) {
            return redirect()->to('/verify');
        }

        $user = $request->user();
        if ($user && !$user->rsi_verified_at) {
            return redirect()->to('/verify');
        }

        return $next($request);
    }
}
