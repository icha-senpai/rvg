<?php

namespace App\Http\Controllers\Api\v1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Bot-facing endpoint for looking up the application's identity data for a
 * Discord user id.
 */
class DiscordIdentityController extends Controller
{
    /**
     * Return the stored Discord/RSI identity payload for a Discord id when the
     * shared bot token is valid.
     */
    public function show(Request $request, string $discordId)
    {
        // This endpoint is consumed by trusted bot automation, so it uses the
        // shared bot token rather than a browser-authenticated session.
        if ($request->header('X-BOT-TOKEN') !== config('services.discord.bot_token')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        $user = User::where('discord_id', $discordId)->first();

        if (! $user) {
            return ApiResponse::success(null, null);
        }

        return ApiResponse::success(
            null,
            [
                'discord_id'   => $user->discord_id,
                'rsi_handle'   => $user->rsi_handle,
                'rsi_verified' => ! is_null($user->rsi_verified_at),
            ]
        );
    }
}
