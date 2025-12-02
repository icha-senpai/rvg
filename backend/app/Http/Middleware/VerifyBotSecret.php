<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyBotSecret
{
    public function handle(Request $request, Closure $next)
    {
        $secret = $request->header('X-Bot-Secret');
        
        if ($secret !== config('services.discord.bot_secret')) {
            return response()->json([
                'error' => 'Invalid bot secret'
            ], 401);
        }

        return $next($request);
    }
}