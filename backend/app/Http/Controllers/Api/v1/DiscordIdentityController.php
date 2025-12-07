<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class DiscordIdentityController extends Controller
{
    public function show(Request $request, string $discordId)
    {
        // Very simple shared-secret guard
        if ($request->header('X-BOT-TOKEN') !== config('services.discord.bot_token')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::where('discord_id', $discordId)->first();

        if (!$user) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => [
                'discord_id'   => $user->discord_id,
                'rsi_handle'   => $user->rsi_handle,
                'rsi_verified' => !is_null($user->rsi_verified_at),
            ],
        ]);
    }
}
