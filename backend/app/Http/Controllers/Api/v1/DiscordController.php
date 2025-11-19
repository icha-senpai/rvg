<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;

class DiscordController extends Controller
{
    /**
     * Redirect user to Discord for OAuth login.
     */
    public function redirect()
    {
        return Socialite::driver('discord')->redirect();
    }

    /**
     * handle the Discord callback
     */
    public function callback()
    {
        // Note: stateless() avoids the session/state mismatch in dev environments
        $discordUser = Socialite::driver('discord')->stateless()->user();

        // Generate a 6-character verification code
        $code = strtoupper(Str::random(6));

        // Create or update user using Discord ID as the primary identity
        $user = User::updateOrCreate(
            ['discord_id' => $discordUser->getId()],
            [
                'discord_name' => $discordUser->getName(),
                'discord_avatar' => $discordUser->getAvatar(),
                'verification_code' => $code,
                'verification_expires_at' => now()->addMinutes(10),
            ]
        );

        return ApiResponse::success('Discord linked successfully', [
            'discord_id' => $user->discord_id,
            'discord_name' => $user->discord_name,
            'verification_code' => $code,
            'message' => 'Paste this code into your RSI short bio, then call /api/verify-rsi.'
        ]);
    }
}
