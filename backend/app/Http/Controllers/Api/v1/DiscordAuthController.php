<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DiscordOAuthService;
use App\Services\TokenService;

class DiscordAuthController extends Controller
{
    protected DiscordOAuthService $discord;
    protected TokenService $tokens;

    public function __construct(DiscordOAuthService $discord, TokenService $tokens)
    {
        $this->discord = $discord;
        $this->tokens  = $tokens;
    }

    public function redirect()
    {
        return $this->discord->redirect();
    }

    public function callback()
{
    try {
        $discordUser = $this->discord->getUser(stateless: true);
    } catch (\Exception $e) {
        return redirect('/verify?error=oauth');
    }

    $user = $this->discord->syncBasicUser($discordUser);

    // Make tokens
    $tokens = $this->tokens->createTokensFor($user);

    // 🚨 DO NOT DO ANY LOGIC HERE
    // Just yeet the user back with token
    return redirect('/verify?token=' . $tokens['access_token']);
}


}
