<?php

namespace App\Services;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

        if ($stateless && $driver instanceof \Laravel\Socialite\Two\AbstractProvider) {
            return $driver->stateless()->user();
        }

        return $driver->user();
    }

    public function checkGuildMembership(?string $discordId): ?bool
    {
        // No Discord ID? No guild.
        if (!$discordId) {
            return false;
        }

        // Global toggle – can ship system before bot is live.
        if (!config('services.discord.guild_check')) {
            return true;
        }

        $guildId  = config('services.discord.guild_id');
        $botToken = config('services.discord.bot_token');

        if (!$guildId || !$botToken) {
            Log::warning('Discord guild check skipped: missing GUILD_ID or BOT_TOKEN.');
            return null;
        }

        $cacheKey = "discord_guild_member:{$guildId}:{$discordId}";

        $client = new Client([
            'base_uri' => 'https://discord.com/api/v10/',
            'timeout'  => 8,
            'connect_timeout' => 4,
            'http_errors' => false,
        ]);

        try {
            $response = $client->get("guilds/{$guildId}/members/{$discordId}", [
                'headers' => [
                    'Authorization' => "Bot {$botToken}",
                    'Accept'        => 'application/json',
                    'User-Agent'    => 'Horizon Interstellar Guild Check (https://horizoninterstellar)',
                ],
            ]);

            if ($response->getStatusCode() === 404) {
                return false;
            }

            if ($response->getStatusCode() !== 200) {
                Log::warning('Discord guild membership check returned non-200', [
                    'discord_id' => $discordId,
                    'status'     => $response->getStatusCode(),
                ]);

                if (Cache::get($cacheKey) === true) {
                    return true;
                }

                return null;
            }

            $payload = json_decode((string) $response->getBody(), true);

            if (!is_array($payload) || $payload === [] || !array_key_exists('joined_at', $payload)) {
                Log::warning('Discord guild membership check returned an unexpected payload', [
                    'discord_id' => $discordId,
                    'status'     => $response->getStatusCode(),
                    'content_type' => $response->getHeaderLine('Content-Type'),
                ]);

                if (Cache::get($cacheKey) === true) {
                    return true;
                }

                return null;
            }

            if (is_array($payload) && array_key_exists('pending', $payload) && $payload['pending'] === true) {
                return false;
            }

            Cache::put($cacheKey, true, now()->addMinutes(30));

            return true;
        } catch (\Throwable $e) {
            Log::error('Discord guild membership check failed', [
                'discord_id' => $discordId,
                'error'      => $e->getMessage(),
            ]);

            if (Cache::get($cacheKey) === true) {
                return true;
            }

            return null;
        }
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
        $code = strtoupper(Str::random(3)) . '-' . rand(100, 999);

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

    public function isMemberOfGuild(string $discordId, string $guildId): bool
    {
        if (!config('services.discord.guild_check')) {
            return true;
        }

        $botToken = config('services.discord.bot_token');

        if (!$guildId || !$botToken) {
            Log::warning('Discord guild check failed: missing GUILD_ID or BOT_TOKEN.');
            return false;
        }

        $client = new Client([
            'base_uri' => 'https://discord.com/api/v10/',
            'timeout'  => 5,
            'http_errors' => false,
        ]);

        try {
            $response = $client->get("guilds/{$guildId}/members/{$discordId}", [
                'headers' => [
                    'Authorization' => "Bot {$botToken}",
                    'Accept'        => 'application/json',
                    'User-Agent'    => 'Horizon Interstellar Guild Check (https://horizoninterstellar)',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $payload = json_decode((string) $response->getBody(), true);

            if (!is_array($payload) || $payload === [] || !array_key_exists('joined_at', $payload)) {
                Log::warning('Discord guild membership check returned an unexpected payload', [
                    'discord_id' => $discordId,
                    'guild_id'   => $guildId,
                    'status'     => $response->getStatusCode(),
                    'content_type' => $response->getHeaderLine('Content-Type'),
                ]);

                return false;
            }

            if (is_array($payload) && array_key_exists('pending', $payload) && $payload['pending'] === true) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Discord guild membership check failed', [
                'discord_id' => $discordId,
                'guild_id' => $guildId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
