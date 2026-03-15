<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Checks whether a Discord user belongs to the required guild.
 *
 * The result is tri-state:
 * - true: confirmed guild member
 * - false: confirmed non-member or still pending guild membership screening
 * - null: Discord could not be checked reliably right now
 */
class DiscordGuildMembershipService
{
    /**
     * Check membership using the default configured guild and bot token.
     */
    public function checkMembership(?string $discordId): ?bool
    {
        return $this->checkMembershipInGuild(
            $discordId,
            config('services.discord.guild_id'),
            config('services.discord.bot_token')
        );
    }

    /**
     * Convenience wrapper for strict boolean membership checks against a specific
     * guild id.
     */
    public function isMemberOfGuild(string $discordId, string $guildId): bool
    {
        return $this->checkMembershipInGuild(
            $discordId,
            $guildId,
            config('services.discord.bot_token')
        ) === true;
    }

    /**
     * Perform the actual Discord guild membership check with outage-tolerant
     * behavior.
     */
    public function checkMembershipInGuild(?string $discordId, ?string $guildId, ?string $botToken): ?bool
    {
        if (! $discordId) {
            return false;
        }

        // A disabled guild check is treated as "allow" so local/dev or emergency
        // configurations do not block login.
        if (! config('services.discord.guild_check')) {
            return true;
        }

        if (! $guildId || ! $botToken) {
            Log::warning('Discord guild check skipped: missing GUILD_ID or BOT_TOKEN.');

            return null;
        }

        $cacheKey = $this->cacheKey($guildId, $discordId);
        $client = new Client([
            'base_uri' => 'https://discord.com/api/v10/',
            'timeout' => 8,
            'connect_timeout' => 4,
            'http_errors' => false,
        ]);

        try {
            $response = $client->get("guilds/{$guildId}/members/{$discordId}", [
                'headers' => [
                    'Authorization' => "Bot {$botToken}",
                    'Accept' => 'application/json',
                    'User-Agent' => 'Horizon Interstellar Guild Check (https://horizoninterstellar)',
                ],
            ]);

            // Discord returns 404 when the user is not in the guild. In that case
            // we clear the positive cache so a past membership cannot linger.
            if ($response->getStatusCode() === 404) {
                Cache::forget($cacheKey);

                return false;
            }

            if ($response->getStatusCode() !== 200) {
                Log::warning('Discord guild membership check returned non-200', [
                    'discord_id' => $discordId,
                    'guild_id' => $guildId,
                    'status' => $response->getStatusCode(),
                ]);

                // If Discord is flaky but we recently confirmed membership, allow a
                // short grace period instead of failing closed immediately.
                if (Cache::get($cacheKey) === true) {
                    return true;
                }

                return null;
            }

            $payload = json_decode((string) $response->getBody(), true);

            if (! is_array($payload) || $payload === [] || ! array_key_exists('joined_at', $payload)) {
                Log::warning('Discord guild membership check returned an unexpected payload', [
                    'discord_id' => $discordId,
                    'guild_id' => $guildId,
                    'status' => $response->getStatusCode(),
                    'content_type' => $response->getHeaderLine('Content-Type'),
                ]);

                // If Discord is flaky but we recently confirmed membership, allow a
                // short grace period instead of failing closed immediately.
                if (Cache::get($cacheKey) === true) {
                    return true;
                }

                return null;
            }

            if (array_key_exists('pending', $payload) && $payload['pending'] === true) {
                Cache::forget($cacheKey);

                return false;
            }

            Cache::put($cacheKey, true, now()->addMinutes(30));

            return true;
        } catch (\Throwable $e) {
            Log::error('Discord guild membership check failed', [
                'discord_id' => $discordId,
                'guild_id' => $guildId,
                'error' => $e->getMessage(),
            ]);

            // Network or API errors become null unless we have a recent confirmed
            // positive membership cached for this user.
            if (Cache::get($cacheKey) === true) {
                return true;
            }

            return null;
        }
    }

    /**
     * Build the cache key for a positive guild-membership confirmation.
     */
    protected function cacheKey(string $guildId, string $discordId): string
    {
        return "discord_guild_member:{$guildId}:{$discordId}";
    }
}
