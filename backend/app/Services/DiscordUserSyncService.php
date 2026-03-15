<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Synchronizes Discord OAuth profile data into local user records.
 */
class DiscordUserSyncService
{
    /**
     * Create or update the local user using the minimum Discord profile fields
     * required for login and display.
     */
    public function syncBasicUser($discordUser): User
    {
        return User::updateOrCreate(
            ['discord_id' => $discordUser->getId()],
            [
                'discord_name' => $discordUser->getName(),
                'discord_avatar' => $discordUser->avatar ?? $discordUser->getAvatar(),
            ]
        );
    }

    /**
     * Create or update the local user while also issuing a short-lived RSI
     * verification code for the manual verification flow.
     */
    public function syncWithVerification($discordUser): array
    {
        $code = strtoupper(Str::random(3)) . '-' . rand(100, 999);

        $user = User::updateOrCreate(
            ['discord_id' => $discordUser->getId()],
            [
                'discord_name' => $discordUser->getName(),
                'discord_avatar' => $discordUser->getAvatar(),
                'verification_code' => $code,
                'verification_expires_at' => now()->addMinutes(10),
            ]
        );

        return [$user, $code];
    }
}
