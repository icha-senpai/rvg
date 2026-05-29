<?php

namespace App\Http\Middleware;

use App\Helpers\WebAuthRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForceDiscordAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (WebAuthRedirect::shouldIgnore($request)) {
            return $next($request);
        }

        // If user is NOT logged in → send them to Discord OAuth
        if (!Auth::check()) {
            return WebAuthRedirect::redirectToVerify($request);
        }

        $user = $request->user();
        if ($user && !$user->rsi_verified_at) {
            return WebAuthRedirect::redirectToVerify($request);
        }

        return $next($request);
    }
}
