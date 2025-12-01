<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Config;

class TokenService
{
    /**
     * Create both access + refresh tokens
     */
    public function createTokensFor(User $user)
    {
        // Determine leadership lifetime
        $isLeadership = $user->rank >= 2;

        $refreshExpiryDays = $isLeadership
            ? 7     // leadership
            : 14;   // regular members

        // ACCESS TOKEN → 24 hours
        $accessToken = $user->createToken('access_token', ['access'], now()->addDay());

        // REFRESH TOKEN → 7 or 14 days
        $refreshToken = $user->createToken(
            'refresh_token',
            ['refresh'],
            now()->addDays($refreshExpiryDays)
        );

        return [
            'access_token'  => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
            'expires_in'    => 86400, // 24 hours
            'refresh_expires_in' => $refreshExpiryDays * 86400,
            'rank' => $user->rank,
            'leadership' => $isLeadership,
        ];
    }

    /**
     * Refresh access token IF refresh token is valid & user still in Discord guild.
     */
    public function refreshAccessToken(User $user, $refreshToken, bool $isInGuild)
    {
        // 1. Kick user if they left Discord
        if (!$isInGuild) {
            return [
                'error' => 'LEFT_GUILD',
                'message' => 'You are no longer in the org Discord.'
            ];
        }

        // 2. Delete old access tokens
        $user->tokens()->where('name', 'access_token')->delete();

        // 3. Issue new access token
        $newAccess = $user->createToken('access_token', ['access'], now()->addDay());

        return [
            'access_token' => $newAccess->plainTextToken,
            'expires_in'   => 86400,
        ];
    }
}
