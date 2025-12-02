<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BotVerificationController extends Controller
{
    /**
     * Verify a Discord user
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'discord_id' => 'required|string',
        ]);

        $user = User::where('discord_id', $validated['discord_id'])->firstOrFail();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'discord_id' => $user->discord_id,
                'rsi_handle' => $user->rsi_handle,
                'is_verified' => (bool) $user->rsi_verified_at,
            ]
        ]);
    }

    /**
     * Get user information
     */
    public function getUser($discordId)
    {
        $user = User::where('discord_id', $discordId)->firstOrFail();

        return response()->json([
            'success' => true,
            'user' => $user->makeVisible(['rsi_handle', 'discord_id'])
        ]);
    }

    /**
     * Check if user is a member of the guild
     */
    public function checkGuildMembership($discordId)
    {
        $user = User::where('discord_id', $discordId)->firstOrFail();
        
        $isMember = $this->checkDiscordGuildMembership($discordId);

        return response()->json([
            'is_member' => $isMember,
            'user_id' => $user->id,
            'discord_id' => $discordId
        ]);
    }

    /**
     * Check Discord guild membership
     */
    protected function checkDiscordGuildMembership($discordId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bot ' . config('services.discord.bot_token')
        ])->get("https://discord.com/api/guilds/" . config('services.discord.guild_id') . "/members/{$discordId}");

        return $response->successful();
    }
}