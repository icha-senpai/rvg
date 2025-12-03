<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\DiscordOAuthService;
use App\Services\TokenService;
use App\Services\DiscordLogger;
use Illuminate\Http\Request;

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

    public function callback(Request $request)
    {
        try {
            $discordUser = $this->discord->getUser(stateless: true);
            
            $user = $this->discord->syncBasicUser($discordUser);
            
            // Log successful auth
            DiscordLogger::auth('login_success', [
                'user_id' => $user->id,
                'discord_id' => $discordUser->getId(),
                'discord_username' => $discordUser->getNickname() ?? $discordUser->getName(),
                'user_agent' => $request->userAgent()
            ]);

            $tokens = $this->tokens->createTokensFor($user);
            return redirect('/verify?token=' . $tokens['access_token']);
            
        } catch (\Exception $e) {
            // Log auth failure
            DiscordLogger::auth('login_failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            return redirect('/verify?error=oauth');
        }
    }


}
