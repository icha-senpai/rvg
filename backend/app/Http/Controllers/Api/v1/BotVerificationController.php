<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DiscordGuildMembershipService;
use Illuminate\Http\Request;

/**
 * Bot-facing verification endpoints used by trusted internal Discord tooling.
 *
 * These endpoints intentionally return limited user data and treat unknown users
 * as a normal 204 case for bot-safe polling behavior.
 */
class BotVerificationController extends Controller
{
    public function __construct(
        protected DiscordGuildMembershipService $guilds,
    ) {}

    /**
     * Return the verification status for a known Discord user during an active
     * bot-driven verification flow.
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'discord_id' => 'required|string',
        ]);

        $user = User::where('discord_id', $validated['discord_id'])->firstOrFail();

        return response()->json([
            'status' => 'success',
            'message' => null,
            'success' => true,
            'user' => [
                'id'          => $user->id,
                'discord_id'  => $user->discord_id,
                'rsi_handle'  => $user->rsi_handle,
                'is_verified' => (bool) $user->rsi_verified_at,
            ],
        ]);
    }

    /**
     * Return bot-safe user information for a Discord id.
     *
     * Unknown users are a normal result here, so the endpoint returns 204 rather
     * than treating that case as an application error.
     */
    public function getUser($discordId)
    {
        $user = User::where('discord_id', $discordId)->first();

        if (! $user) {
            return response()->noContent();
        }

        return response()->json([
            'status' => 'success',
            'message' => null,
            'success' => true,
            'user' => [
                'id'              => $user->id,
                'discord_id'      => $user->discord_id,
                'rsi_handle'      => $user->rsi_handle,
                'is_verified'     => (bool) $user->rsi_verified_at,
                'rsi_verified_at' => $user->rsi_verified_at,
            ],
        ]);
    }

    /**
     * Return whether the user is currently considered a guild member.
     *
     * The shared guild service uses tri-state behavior internally, but this bot
     * endpoint intentionally collapses that into a strict boolean response.
     */
    public function checkGuildMembership($discordId)
    {
        $user = User::where('discord_id', $discordId)->first();

        if (! $user) {
            return response()->noContent();
        }

        $isMember = $this->guilds->checkMembership($discordId) === true;

        return response()->json([
            'status' => 'success',
            'message' => null,
            'is_member'  => $isMember,
            'user_id'    => $user->id,
            'discord_id' => $discordId,
        ]);
    }
}
