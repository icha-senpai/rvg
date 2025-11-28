<?php

namespace App\Services;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Str;

class DiscordOAuthService
{
    /**
     * Redirect to Discord for OAuth.
     */
    public function redirect()
    {
        return Socialite::driver('discord')->redirect();
    }

    /**
     * Get the Discord user from the callback.
     *
     * @param  bool  $stateless
     * @return \Laravel\Socialite\Contracts\User
     * @throws \Exception
     */
    public function getUser(bool $stateless = false)
    {
        $driver = Socialite::driver('discord');

        if ($stateless) {
            $driver = $driver->stateless();
        }

        return $driver->user();
    }

    /**
     * Basic "login with Discord" user sync.
     * Used by DiscordAuthController.
     */
    public function syncBasicUser($discordUser): User
    {
        return User::updateOrCreate(
            ['discord_id' => $discordUser->getId()],
            [
                'discord_name'   => $discordUser->getName(),
                // handle either property or method, depending on Socialite version
                'discord_avatar' => $discordUser->avatar ?? $discordUser->getAvatar(),
            ]
        );
    }

    /**
     * "Link Discord + generate verification code" flow.
     * Used by DiscordController.
     *
     * @return array [User $user, string $code]
     */
    public function syncWithVerification($discordUser): array
    {
        $code = strtoupper(Str::random(6));

        $user = User::updateOrCreate(
            ['discord_id' => $discordUser->getId()],
            [
                'discord_name'              => $discordUser->getName(),
                'discord_avatar'            => $discordUser->getAvatar(),
                'verification_code'         => $code,
                'verification_expires_at'   => now()->addMinutes(10),
            ]
        );

        return [$user, $code];
    }
}
