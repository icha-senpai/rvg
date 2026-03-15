<?php

namespace App\Services;

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;

/**
 * Handles Discord OAuth transport while delegating guild checks and user syncing
 * to focused services.
 *
 * This keeps the Socialite-specific code separate from membership policy and
 * user persistence concerns.
 */
class DiscordOAuthService
{
    public function __construct(
        protected DiscordGuildMembershipService $guilds,
        protected DiscordUserSyncService $users,
    ) {}

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

        // Some callback flows intentionally skip session state verification,
        // so this wrapper keeps the controller from needing provider details.
        if ($stateless && $driver instanceof \Laravel\Socialite\Two\AbstractProvider) {
            return $driver->stateless()->user();
        }

        return $driver->user();
    }

    /**
     * Backward-compatible wrapper for the shared guild membership check.
     */
    public function checkGuildMembership(?string $discordId): ?bool
    {
        return $this->guilds->checkMembership($discordId);
    }

    /**
     * Backward-compatible wrapper for syncing the basic Discord user profile.
     */
    public function syncBasicUser($discordUser): User
    {
        return $this->users->syncBasicUser($discordUser);
    }

    /**
     * Backward-compatible wrapper for syncing the Discord user and generating an
     * RSI verification code in one step.
     */
    public function syncWithVerification($discordUser): array
    {
        return $this->users->syncWithVerification($discordUser);
    }

    /**
     * Check membership in an explicitly provided guild.
     */
    public function isMemberOfGuild(string $discordId, string $guildId): bool
    {
        return $this->guilds->isMemberOfGuild($discordId, $guildId);
    }
}
