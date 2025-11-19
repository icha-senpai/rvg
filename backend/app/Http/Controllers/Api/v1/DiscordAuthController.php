<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use App\Helpers\ApiResponse;

class DiscordAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('discord')->redirect();
    }

    public function callback()
    {
        try {
            $discordUser = Socialite::driver('discord')->user();
        } catch (\Exception $e) {
            return response()->json(['error' => 'OAuth failed', 'details' => $e->getMessage()], 400);
        }

        $user = User::updateOrCreate(
            ['discord_id' => $discordUser->getId()],
            [
                'discord_name' => $discordUser->getName(),
                'discord_avatar' => $discordUser->avatar,
            ]
        );

        return ApiResponse::success('User authenticated successfully', $user);
    }
}
