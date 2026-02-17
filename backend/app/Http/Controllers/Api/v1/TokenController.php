<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\TokenService;
use App\Services\DiscordOAuthService;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    protected TokenService $tokens;
    protected DiscordOAuthService $discord;

    public function __construct(TokenService $tokens, DiscordOAuthService $discord)
    {
        $this->tokens  = $tokens;
        $this->discord = $discord;
    }

    public function refresh(Request $request)
    {
        $raw = $request->bearerToken();

        if (!$raw) {
            return response()->json([
                'status' => 'error',
                'message' => 'No refresh token provided',
            ], 401);
        }

        $pat = PersonalAccessToken::findToken($raw);

        if (!$pat || !in_array('refresh', $pat->abilities ?? [])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid refresh token',
            ], 401);
        }

        if ($pat->expires_at && $pat->expires_at->isPast()) {
            $pat->delete();
            return response()->json([
                'status' => 'error',
                'message' => 'Refresh token expired',
            ], 401);
        }

        $user = $pat->tokenable;

        if (!$user) {
            $pat->delete();
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // 🔍 Guild check (toggleable with DISCORD_GUILD_CHECK)
        $isInGuild = $this->discord->checkGuildMembership($user->discord_id);

        if ($isInGuild === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Discord guild membership check is temporarily unavailable. Please try again.',
                'state' => 'GUILD_CHECK_UNAVAILABLE',
            ], 503);
        }

        $result = $this->tokens->refreshAccessToken($user, $pat, $isInGuild);

        if (isset($result['error'])) {
            // User left guild or failed check → nuke tokens
            $pat->delete();
            $user->tokens()->delete();

            return response()->json(
                array_merge(['status' => 'error'], $result),
                403
            );
        }

        // Valid refresh → return new access token
        return response()->json(
            array_merge([
                'status' => 'success',
                'message' => 'Access token refreshed.',
            ], $result)
        );
    }
}
