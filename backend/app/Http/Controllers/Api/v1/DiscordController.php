<?php

namespace App\Http\Controllers\Api\V1;


use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Services\DiscordOAuthService;

class DiscordController extends Controller
{
    protected $discord;

    public function __construct(DiscordOAuthService $discord)
    {
        $this->discord = $discord;
    }
    /**
     * Redirect user to Discord for OAuth login.
     */
    public function redirect()
    {
        return $this->discord->redirect();

    }

    /**
     * handle the Discord callback
     */
    public function callback()
    {
        // Get Discord user using stateless mode
        $discordUser = $this->discord->getUser(stateless: true);

        // Sync user + generate verification code
        [$user, $code] = $this->discord->syncWithVerification($discordUser);

        return ApiResponse::success('Discord linked successfully', [
            'discord_id' => $user->discord_id,
            'discord_name' => $user->discord_name,
            'verification_code' => $code,
            'message' => 'Paste this code into your RSI short bio, then call /api/verify-rsi.',
        ]);
    }
}
